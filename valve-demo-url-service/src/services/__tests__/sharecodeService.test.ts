import { SharecodeService } from '../sharecodeService';

describe('SharecodeService', () => {
    describe('validateSharecode', () => {
        it('should validate correct sharecode format', () => {
            const validSharecode = 'CSGO-2vCuH-4GBwh-GLrjU-nz8Rf-L5WwP';
            expect(SharecodeService.validateSharecode(validSharecode)).toBe(true);
        });

        it('should reject invalid sharecode format', () => {
            const invalidSharecodes = [
                'invalid-sharecode',
                'CSGO-invalid',
                'CSGO-2vCuH-4GBwh-GLrjU-nz8Rf',
                'csgo-2vCuH-4GBwh-GLrjU-nz8Rf-L5WwP',
                '',
                null,
                undefined
            ];

            invalidSharecodes.forEach(sharecode => {
                expect(SharecodeService.validateSharecode(sharecode as any)).toBe(false);
            });
        });

        it('should handle whitespace in sharecode', () => {
            const sharecodeWithSpaces = '  CSGO-2vCuH-4GBwh-GLrjU-nz8Rf-L5WwP  ';
            expect(SharecodeService.validateSharecode(sharecodeWithSpaces)).toBe(true);
        });
    });
});
