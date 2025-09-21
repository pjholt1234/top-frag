import logger from '../utils/logger';
import { SteamService } from './steamService';

interface RoundStats {
    map?: string;
    [key: string]: any;
}

interface WatchableMatchInfo {
    server_ip?: string;
    tv_port?: string;
    [key: string]: any;
}

interface GameData {
    roundstatsall?: RoundStats[];
    watchablematchinfo?: WatchableMatchInfo;
    demoUrl?: string;
    [key: string]: any;
}

export class DemoService {
    private steamService: SteamService;

    constructor(steamService: SteamService) {
        this.steamService = steamService;
    }

    async getDemoUrl(sharecode: string): Promise<string | null> {
        if (!this.steamService.isGCReady()) {
            throw new Error('CS2 Game Coordinator not ready');
        }

        const gameData = await this.steamService.requestMatchInfo(sharecode);

        if (!gameData) {
            return null;
        }

        const demoUrl = this.extractDemoUrl(gameData);

        if (!demoUrl) {
            logger.warn('No demo URL found in game data', { sharecode });
            return null;
        }

        return demoUrl;
    }

    private extractDemoUrl(gameData: GameData): string | null {
        if (gameData.roundstatsall && Array.isArray(gameData.roundstatsall)) {
            const lastRound = gameData.roundstatsall[gameData.roundstatsall.length - 1];
            if (lastRound && lastRound.map && typeof lastRound.map === 'string' && lastRound.map.includes('.dem')) {
                return lastRound.map;
            }
        }

        if (gameData.watchablematchinfo) {
            const watchInfo = gameData.watchablematchinfo;
            if (watchInfo.server_ip && watchInfo.tv_port) {
                return `http://replay${watchInfo.server_ip}.valve.net/730/${watchInfo.tv_port}.dem`;
            }
        }

        if (gameData.demoUrl) {
            return gameData.demoUrl;
        }

        return null;
    }
}
