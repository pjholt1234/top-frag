import { DemoService } from '../demoService';
import { SteamService } from '../steamService';

interface MatchInfo {
    matchid: string;
    matchtime: number;
    roundstatsall?: any[];
    watchablematchinfo?: any;
}

describe('DemoService', () => {
    let demoService: DemoService;
    let mockSteamService: jest.Mocked<SteamService>;

    beforeEach(() => {
        mockSteamService = {
            isGCReady: jest.fn(),
            requestMatchInfo: jest.fn()
        } as any;

        demoService = new DemoService(mockSteamService);
    });

    describe('getDemoUrl', () => {
        it('should throw error when GC is not ready', async () => {
            mockSteamService.isGCReady.mockReturnValue(false);

            await expect(demoService.getDemoUrl('CSGO-test-sharecode'))
                .rejects.toThrow('CS2 Game Coordinator not ready');
        });

        it('should return demo URL from roundstatsall map field', async () => {
            mockSteamService.isGCReady.mockReturnValue(true);
            const mockMatchInfo: MatchInfo = {
                matchid: '123456789',
                matchtime: 1640995200,
                roundstatsall: [
                    { map: null },
                    { map: 'http://replay192.valve.net/730/test.dem.bz2' }
                ]
            };
            mockSteamService.requestMatchInfo.mockResolvedValue(mockMatchInfo);

            const result = await demoService.getDemoUrl('CSGO-test-sharecode');
            expect(result).toBe('http://replay192.valve.net/730/test.dem.bz2');
        });

        it('should return demo URL from watchablematchinfo', async () => {
            mockSteamService.isGCReady.mockReturnValue(true);
            const mockMatchInfo: MatchInfo = {
                matchid: '123456789',
                matchtime: 1640995200,
                roundstatsall: [{ map: null }],
                watchablematchinfo: {
                    server_ip: 192,
                    tv_port: 12345
                }
            };
            mockSteamService.requestMatchInfo.mockResolvedValue(mockMatchInfo);

            const result = await demoService.getDemoUrl('CSGO-test-sharecode');
            expect(result).toBe('http://replay192.valve.net/730/12345.dem');
        });

        it('should return null when no demo URL found', async () => {
            mockSteamService.isGCReady.mockReturnValue(true);
            const mockMatchInfo: MatchInfo = {
                matchid: '123456789',
                matchtime: 1640995200,
                roundstatsall: [{ map: null }],
                watchablematchinfo: {}
            };
            mockSteamService.requestMatchInfo.mockResolvedValue(mockMatchInfo);

            const result = await demoService.getDemoUrl('CSGO-test-sharecode');
            expect(result).toBeNull();
        });
    });
});
