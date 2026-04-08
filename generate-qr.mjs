import QRCode from 'qrcode';
import { writeFileSync } from 'fs';

const url    = 'http://localhost:8000/sugerencias';
const output = 'public/sugerencias-qr.png';

await QRCode.toFile(output, url, {
    type:           'png',
    width:          800,
    margin:         3,
    color: {
        dark:  '#166534',
        light: '#ffffff',
    },
    errorCorrectionLevel: 'M',
});

console.log(`QR generado en: ${output}`);
