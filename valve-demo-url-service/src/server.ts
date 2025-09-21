import express, { Request, Response, NextFunction } from 'express';
import cors from 'cors';
import helmet from 'helmet';
import rateLimit from 'express-rate-limit';
import { body, validationResult, ValidationChain } from 'express-validator';
import dotenv from 'dotenv';
import logger from './utils/logger';
import { SteamService } from './services/steamService';
import { DemoService } from './services/demoService';
import { SharecodeService } from './services/sharecodeService';

dotenv.config();

const app = express();
const PORT = process.env['PORT'] || 3001;

const steamService = new SteamService();
const demoService = new DemoService(steamService);

const metrics = {
    requests: 0,
    errors: 0,
    demosProcessed: 0,
    startTime: new Date().toISOString()
};

const limiter = rateLimit({
    windowMs: parseInt(process.env['RATE_LIMIT_WINDOW'] || '900000'),
    max: parseInt(process.env['RATE_LIMIT_MAX'] || '100'),
    message: {
        error: 'Too many requests, please try again later.',
        retryAfter: Math.ceil(parseInt(process.env['RATE_LIMIT_WINDOW'] || '900000') / 1000)
    },
    standardHeaders: true,
    legacyHeaders: false
});

const authenticateApiKey = (req: Request, res: Response, next: NextFunction): void => {
    const apiKey = req.headers['x-api-key'] as string ||
        req.headers.authorization?.replace('Bearer ', '');

    const validApiKeys = process.env['API_KEYS']?.split(',') || [];

    if (!apiKey || !validApiKeys.includes(apiKey)) {
        res.status(401).json({
            error: 'Invalid or missing API key',
            timestamp: new Date().toISOString()
        });
        return;
    }

    next();
};

app.use(helmet({
    contentSecurityPolicy: {
        directives: {
            defaultSrc: ['\'self\''],
            styleSrc: ['\'self\'', '\'unsafe-inline\''],
            scriptSrc: ['\'self\''],
            imgSrc: ['\'self\'', 'data:', 'https:']
        }
    },
    crossOriginEmbedderPolicy: false
}));

app.use(cors({
    origin: process.env['ALLOWED_ORIGINS'] ? process.env['ALLOWED_ORIGINS'].split(',') : '*',
    credentials: true,
    methods: ['GET', 'POST'],
    allowedHeaders: ['Content-Type', 'Authorization', 'X-API-Key']
}));

app.use(express.json({ limit: '1mb' }));

app.use((req: Request, _res: Response, next: NextFunction) => {
    metrics.requests++;
    logger.info(`${req.method} ${req.path}`, {
        ip: req.ip,
        userAgent: req.get('User-Agent')
    });
    next();
});

app.get('/health', (_req: Request, res: Response) => {
    const health = {
        status: 'healthy',
        timestamp: new Date().toISOString(),
        uptime: process.uptime(),
        version: process.env['npm_package_version'] || '1.0.0',
        environment: process.env['NODE_ENV'] || 'development',
        dependencies: {
            steam: steamService.isGCReady() ? 'connected' : 'disconnected',
            gameCoordinator: steamService.isGCReady() ? 'ready' : 'not ready'
        },
        system: {
            memory: process.memoryUsage(),
            platform: process.platform,
            nodeVersion: process.version
        }
    };

    res.json(health);
});

app.get('/metrics', authenticateApiKey, (_req: Request, res: Response) => {
    const uptime = process.uptime();
    const memoryUsage = process.memoryUsage();

    const metricsData = {
        ...metrics,
        uptime: Math.floor(uptime),
        memory: {
            rss: Math.round(memoryUsage.rss / 1024 / 1024),
            heapTotal: Math.round(memoryUsage.heapTotal / 1024 / 1024),
            heapUsed: Math.round(memoryUsage.heapUsed / 1024 / 1024),
            external: Math.round(memoryUsage.external / 1024 / 1024)
        },
        timestamp: new Date().toISOString()
    };

    res.json(metricsData);
});

const demoValidation: ValidationChain[] = [
    body('sharecode')
        .notEmpty()
        .withMessage('Sharecode is required')
        .isLength({ min: 34, max: 34 })
        .withMessage('Sharecode must be exactly 34 characters')
        .matches(/^CSGO-[A-Za-z0-9]{5}-[A-Za-z0-9]{5}-[A-Za-z0-9]{5}-[A-Za-z0-9]{5}-[A-Za-z0-9]{5}$/)
        .withMessage('Invalid sharecode format')
        .customSanitizer((value: string) => value.trim())
];

app.post('/demo',
    limiter,
    authenticateApiKey,
    demoValidation,
    async (req: Request, res: Response) => {
        try {
            const errors = validationResult(req);
            if (!errors.isEmpty()) {
                return res.status(400).json({
                    error: 'Validation failed',
                    details: errors.array(),
                    timestamp: new Date().toISOString()
                });
            }

            const { sharecode } = req.body;

            if (!SharecodeService.validateSharecode(sharecode)) {
                return res.status(400).json({
                    error: 'Invalid sharecode format',
                    timestamp: new Date().toISOString()
                });
            }

            const demoUrl = await demoService.getDemoUrl(sharecode);

            if (!demoUrl) {
                return res.status(404).json({
                    error: 'Demo not found or unavailable',
                    sharecode,
                    timestamp: new Date().toISOString()
                });
            }

            metrics.demosProcessed++;

            res.json({
                matchId: sharecode,
                demoUrl,
                service: 'sharecode-decoder',
                timestamp: new Date().toISOString()
            });

        } catch (error: any) {
            metrics.errors++;
            logger.error('Error processing demo request', {
                error: error.message,
                sharecode: req.body.sharecode,
                stack: error.stack
            });

            res.status(500).json({
                error: 'Internal server error',
                message: error.message,
                timestamp: new Date().toISOString()
            });
        }
    }
);

app.use((_req: Request, res: Response) => {
    res.status(404).json({
        error: 'Endpoint not found',
        timestamp: new Date().toISOString()
    });
});

app.use((error: Error, _req: Request, res: Response, _next: NextFunction) => {
    metrics.errors++;
    logger.error('Unhandled error', { error: error.message, stack: error.stack });

    res.status(500).json({
        error: 'Internal server error',
        timestamp: new Date().toISOString()
    });
});

async function startServer(): Promise<void> {
    try {
        logger.info('Initializing Steam services...');
        await steamService.initialize();

        app.listen(PORT, () => {
            logger.info(`Sharecode Decoder Service started on port ${PORT}`);
        });
    } catch (error: any) {
        logger.error('Failed to start server', { error: error.message });
        process.exit(1);
    }
}

startServer();
