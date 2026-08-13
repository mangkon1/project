/**
 * Barcode Scanner Module — ใช้ html5-qrcode library
 * รองรับ 1D Barcode และ QR Code ผ่านกล้องมือถือ/เว็บแคม
 */

class BarcodeScanner {
    constructor(options = {}) {
        this.targetInputId = options.targetInputId || null;
        this.onScanSuccess  = options.onScanSuccess  || null;
        this.modalId        = options.modalId || 'barcodeScannerModal';
        this.scanner        = null;
        this.isRunning      = false;
    }

    // เปิด Modal สแกน
    open(targetInputId = null) {
        if (targetInputId) this.targetInputId = targetInputId;

        const modal = document.getElementById(this.modalId);
        if (!modal) { console.error('Scanner modal not found:', this.modalId); return; }
        modal.classList.add('show');

        // โหลด html5-qrcode หากยังไม่ได้โหลด
        if (typeof Html5Qrcode === 'undefined') {
            const script = document.createElement('script');
            script.src = 'https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js';
            script.onload = () => this._startScanner();
            document.head.appendChild(script);
        } else {
            this._startScanner();
        }
    }

    // ปิด Modal
    close() {
        this._stopScanner();
        const modal = document.getElementById(this.modalId);
        if (modal) modal.classList.remove('show');
    }

    // เริ่มกล้อง
    _startScanner() {
        if (this.isRunning) return;

        const readerId = 'html5-qr-reader';
        const readerEl = document.getElementById(readerId);
        if (!readerEl) return;

        // Clear previous instance
        readerEl.innerHTML = '';

        try {
            this.scanner = new Html5Qrcode(readerId);
            const config = {
                fps: 10,
                qrbox: { width: 250, height: 150 },
                aspectRatio: 1.777,
                formatsToSupport: [
                    Html5QrcodeSupportedFormats.CODE_128,
                    Html5QrcodeSupportedFormats.CODE_39,
                    Html5QrcodeSupportedFormats.EAN_13,
                    Html5QrcodeSupportedFormats.EAN_8,
                    Html5QrcodeSupportedFormats.QR_CODE,
                    Html5QrcodeSupportedFormats.DATA_MATRIX,
                ]
            };

            this.scanner.start(
                { facingMode: 'environment' }, // ใช้กล้องหลังก่อน
                config,
                (decodedText) => this._onScan(decodedText),
                (errorMsg) => { /* ignore */ }
            ).then(() => {
                this.isRunning = true;
                document.getElementById('scannerStatus').textContent = '🟢 กล้องพร้อมแล้ว — เล็งที่บาร์โค้ด';
            }).catch((err) => {
                console.warn('Camera back failed, trying front:', err);
                // Fallback: กล้องหน้า
                this.scanner.start(
                    { facingMode: 'user' },
                    config,
                    (decodedText) => this._onScan(decodedText),
                    () => {}
                ).then(() => {
                    this.isRunning = true;
                    document.getElementById('scannerStatus').textContent = '🟡 ใช้กล้องหน้า — เล็งที่บาร์โค้ด';
                }).catch(() => {
                    document.getElementById('scannerStatus').textContent = '🔴 ไม่พบกล้อง — กรุณากรอกเอง';
                });
            });
        } catch (e) {
            document.getElementById('scannerStatus').textContent = '🔴 เกิดข้อผิดพลาด: ' + e.message;
        }
    }

    // หยุดกล้อง
    _stopScanner() {
        if (this.scanner && this.isRunning) {
            this.scanner.stop().then(() => {
                this.scanner.clear();
                this.isRunning = false;
            }).catch(() => { this.isRunning = false; });
        }
    }

    // เมื่อสแกนสำเร็จ
    _onScan(text) {
        text = text.trim();
        if (!text) return;

        // เสียง feedback
        this._beep();

        // ใส่ค่าลงใน input
        if (this.targetInputId) {
            const input = document.getElementById(this.targetInputId);
            if (input) {
                input.value = text;
                input.dispatchEvent(new Event('input', { bubbles: true }));
                input.dispatchEvent(new Event('change', { bubbles: true }));
                input.classList.add('scan-success-flash');
                setTimeout(() => input.classList.remove('scan-success-flash'), 1000);
            }
        }

        // Callback
        if (typeof this.onScanSuccess === 'function') {
            this.onScanSuccess(text);
        }

        // อัปเดต UI
        const lastScan = document.getElementById('lastScannedValue');
        if (lastScan) lastScan.textContent = text;

        // ปิด Modal หลังสแกน
        setTimeout(() => this.close(), 800);
    }

    // เสียง Beep
    _beep() {
        try {
            const ctx = new (window.AudioContext || window.webkitAudioContext)();
            const osc = ctx.createOscillator();
            const gain = ctx.createGain();
            osc.connect(gain); gain.connect(ctx.destination);
            osc.type = 'sine'; osc.frequency.setValueAtTime(880, ctx.currentTime);
            gain.gain.setValueAtTime(0.3, ctx.currentTime);
            gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.2);
            osc.start(ctx.currentTime); osc.stop(ctx.currentTime + 0.2);
        } catch (e) { /* ไม่ต้อง handle */ }
    }
}

// ── Global instance ──
window.barcodeScanner = new BarcodeScanner({ modalId: 'barcodeScannerModal' });

// ── Helper functions ──
function openBarcodeScanner(targetInputId, callback) {
    window.barcodeScanner.targetInputId = targetInputId;
    window.barcodeScanner.onScanSuccess = callback || null;
    window.barcodeScanner.open();
}

function closeBarcodeScanner() {
    window.barcodeScanner.close();
}

// CSS for scan success flash
const style = document.createElement('style');
style.textContent = `
.scan-success-flash {
    border-color: #198754 !important;
    background: #d1fae5 !important;
    transition: all .3s !important;
}
#html5-qr-reader { border-radius: 10px; overflow: hidden; }
#html5-qr-reader video { border-radius: 10px; }
#html5-qr-reader__scan_region { background: transparent !important; }
`;
document.head.appendChild(style);
