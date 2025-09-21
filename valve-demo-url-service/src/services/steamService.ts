import logger from '../utils/logger';

const SteamUser = require('steam-user');
const GlobalOffensive = require('globaloffensive');
const SteamTotp = require('steam-totp');

interface MatchInfo {
    matchid: string;
    matchtime: number;
    roundstats_legacy?: any;
    roundstatsall?: any[];
    watchablematchinfo?: any;
    [key: string]: any;
}

interface PendingMatchRequest {
    sharecode: string;
    resolve: (value: MatchInfo) => void;
    reject: (reason: any) => void;
}

export class SteamService {
    private client: any;
    private cs: any;
    private connected: boolean = false;
    private gcReady: boolean = false;
    private pendingMatchRequest: PendingMatchRequest | null = null;

    constructor() {
        this.client = new SteamUser();
        this.cs = new GlobalOffensive(this.client);
        this.setupEventHandlers();
    }

    private setupEventHandlers(): void {
        this.client.on('loggedOn', () => {
            logger.info('Steam client logged on successfully');
            this.connected = true;
            this.client.setPersona(1);
            this.client.gamesPlayed([730]);
        });

        this.client.on('error', (err: Error) => {
            logger.error('Steam client error', { error: err.message });
            this.connected = false;
            this.gcReady = false;
        });

        this.client.on('playingState', (blocked: boolean, playingApp: any) => {
            if (playingApp.appid === 730) {
                logger.info('CS2 app launched, Game Coordinator should be ready');
                this.gcReady = true;
            }
        });

        this.client.on('appLaunched', (appid: number) => {
            if (appid === 730) {
                logger.info('CS2 app launched, Game Coordinator should be ready');
                this.gcReady = true;
            }
        });

        this.cs.on('matchList', (...args: any[]) => {
            let matches: MatchInfo[] = [];
            let matchCount = 0;

            if (Array.isArray(args[0])) {
                matches = args[0];
                matchCount = matches.length;
            } else if (typeof args[0] === 'number') {
                matchCount = args[0];
                matches = args[1] || [];
            } else {
                matches = args.filter(arg => arg && typeof arg === 'object' && arg.matchid);
                matchCount = matches.length;
            }

            logger.info('Match list received', {
                matchCount,
                matches: matches?.length || 0,
                pendingRequest: !!this.pendingMatchRequest,
                firstMatchKeys: matches?.[0] ? Object.keys(matches[0]) : [],
                argsLength: args.length
            });

            if (this.pendingMatchRequest && matches && matches.length > 0) {
                const ourMatch = matches.find(match => match.matchid) || matches[0];

                if (ourMatch) {
                    logger.info('Match info received from Game Coordinator', {
                        sharecode: this.pendingMatchRequest.sharecode,
                        matchId: ourMatch.matchid,
                        hasRoundStatsAll: !!ourMatch.roundstatsall,
                        roundStatsAllLength: ourMatch.roundstatsall?.length || 0,
                        hasWatchableMatchInfo: !!ourMatch.watchablematchinfo
                    });
                    this.pendingMatchRequest.resolve(ourMatch);
                    this.pendingMatchRequest = null;
                } else {
                    logger.warn('No valid match found in matchList', {
                        sharecode: this.pendingMatchRequest.sharecode,
                        matches: matches.map(m => ({ matchid: m.matchid, keys: Object.keys(m) }))
                    });
                }
            }
        });
    }

    async initialize(): Promise<void> {
        return new Promise((resolve, reject) => {
            const timeout = setTimeout(() => {
                reject(new Error('Steam initialization timeout'));
            }, 30000);

            this.client.once('loggedOn', () => {
                clearTimeout(timeout);
                resolve();
            });

            this.client.once('error', (err: Error) => {
                clearTimeout(timeout);
                reject(err);
            });

            const username = process.env['STEAM_USERNAME'];
            const password = process.env['STEAM_PASSWORD'];
            const sharedSecret = process.env['STEAM_SHARED_SECRET'];

            if (!username || !password) {
                reject(new Error('Steam credentials not provided'));
                return;
            }

            const logOnDetails: any = {
                accountName: username,
                password: password
            };

            if (sharedSecret) {
                try {
                    logOnDetails.twoFactorCode = SteamTotp.getAuthCode(sharedSecret);
                } catch (err) {
                    logger.warn('Failed to generate 2FA code, using password-only authentication', { error: err });
                }
            } else {
                logger.warn('No STEAM_SHARED_SECRET provided, using password-only authentication');
            }

            this.client.logOn(logOnDetails);
        });
    }

    isGCReady(): boolean {
        return this.gcReady && this.connected;
    }

    async requestMatchInfo(sharecode: string): Promise<MatchInfo> {
        return new Promise((resolve, reject) => {
            if (!this.gcReady) {
                reject(new Error('CS2 Game Coordinator not ready'));
                return;
            }

            const timeout = setTimeout(() => {
                this.pendingMatchRequest = null;
                reject(new Error('Match info request timeout - match may be too old or unavailable'));
            }, 30000);

            this.pendingMatchRequest = {
                sharecode,
                resolve: (value) => {
                    clearTimeout(timeout);
                    resolve(value);
                },
                reject: (reason) => {
                    clearTimeout(timeout);
                    reject(reason);
                }
            };

            logger.info('Requesting match info from Game Coordinator', {
                sharecode,
                sharecodeLength: sharecode.length,
                sharecodeType: typeof sharecode
            });

            setTimeout(() => {
                logger.info('Calling requestGame with sharecode', { sharecode });
                this.cs.requestGame(sharecode);
            }, 1000);
        });
    }
}
