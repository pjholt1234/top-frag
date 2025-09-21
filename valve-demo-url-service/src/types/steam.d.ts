declare module 'steam-user' {
    interface SteamUserOptions {
        dataDirectory?: string;
        singleSentryfile?: boolean;
        promptSteamGuardCode?: boolean;
        machineId?: string;
        machineIdType?: number;
    }

    interface LogOnDetails {
        accountName: string;
        password: string;
        authCode?: string;
        twoFactorCode?: string;
        loginKey?: string;
        rememberPassword?: boolean;
        accessToken?: string;
        refreshToken?: string;
        anonymous?: boolean;
    }

    interface PlayingState {
        blocked: boolean;
        appid: number;
    }

    interface License {
        packageId: number;
        timeCreated: number;
        timeNextProcess: number;
        minuteLimit: number;
        minutesUsed: number;
        paymentMethod: number;
        flags: number;
        purchaseCountryCode: string;
        licenseType: number;
        territoryCode: number;
        changeNumber: number;
        ownerSteamId: string;
        initialPeriod: number;
        initialTimeUnit: number;
        renewPeriod: number;
        renewTimeUnit: number;
        accessToken: number;
        masterPackageId: number;
    }

    class SteamUser {
        constructor(options?: SteamUserOptions);
        logOn(details: LogOnDetails): void;
        logOff(): void;
        setPersona(state: number, name?: string): void;
        gamesPlayed(games: number | number[] | string | string[]): void;
        on(event: string, listener: (...args: any[]) => void): this;
        on(event: 'loggedOn', listener: (details: any) => void): this;
        on(event: 'error', listener: (err: Error) => void): this;
        on(event: 'playingState', listener: (blocked: boolean, playingApp: PlayingState) => void): this;
        on(event: 'licenses', listener: (licenses: License[]) => void): this;
        on(event: 'appLaunched', listener: (appid: number, playSessionID: string) => void): this;
    }

    export = SteamUser;
}

declare module 'globaloffensive' {
    interface RoundStats {
        assists?: number[];
        deaths?: number[];
        enemy_headshots?: number[];
        enemy_kills?: number[];
        kills?: number[];
        map?: string;
        match_duration?: number;
        match_result?: number;
        max_rounds?: number;
        mvps?: number[];
        scores?: number[];
        team_scores?: number[];
        [key: string]: any;
    }

    interface WatchableMatchInfo {
        cl_decryptdata_key?: string;
        cl_decryptdata_key_pub?: string;
        game_map?: string;
        game_mapgroup?: string;
        game_type?: number;
        match_id?: string;
        reservation_id?: string;
        server_id?: string;
        server_ip?: number;
        tv_port?: number;
        tv_spectators?: number;
        tv_time?: number;
        tv_watch_password?: string;
        [key: string]: any;
    }

    interface MatchInfo {
        matchid: string;
        matchtime: number;
        roundstats_legacy?: any;
        roundstatsall?: RoundStats[];
        watchablematchinfo?: WatchableMatchInfo;
        [key: string]: any;
    }

    interface MatchListEvent {
        matchCount: number;
        matches: MatchInfo[];
    }

    class GlobalOffensive {
        constructor(steamUser: any);
        requestGame(sharecode: string): void;
        on(event: string, listener: (...args: any[]) => void): this;
        on(event: 'matchList', listener: (...args: any[]) => void): this;
    }

    export = GlobalOffensive;
}

declare module 'steam-totp' {
    function getAuthCode(sharedSecret: string, timeOffset?: number): string;
    function getConfirmationKey(identitySecret: string, time: number, tag: string): string;
    function getTimeOffset(callback: (error: Error | null, offset?: number, latency?: number) => void): void;
    function getTimeOffset(): Promise<number>;
    export { getAuthCode, getConfirmationKey, getTimeOffset };
}
