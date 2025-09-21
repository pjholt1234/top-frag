export class SharecodeService {
    static validateSharecode(sharecode: string): boolean {
        if (!sharecode || typeof sharecode !== 'string') {
            return false;
        }

        const sharecodeRegex = /^CSGO-[A-Za-z0-9]{5}-[A-Za-z0-9]{5}-[A-Za-z0-9]{5}-[A-Za-z0-9]{5}-[A-Za-z0-9]{5}$/;
        return sharecodeRegex.test(sharecode.trim());
    }
}
