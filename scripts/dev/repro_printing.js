const printerEngine = require('./src/print-engine/windows/win32-printer');
const path = require('path');
const fs = require('fs');

async function runTests() {
    console.log('🚀 Starting Printing Reproduction Tests...');

    // 1. List Printers
    console.log('\n--- Step 1: Listing Printers ---');
    let printers = [];
    try {
        printers = await printerEngine.getPrinters();
        console.log(`Found ${printers.length} printers.`);
        printers.forEach(p => console.log(` - ${p.name} (${p.status}) ${p.isDefault ? '[DEFAULT]' : ''}`));
    } catch (e) {
        console.error('❌ Failed to list printers:', e);
        return;
    }

    if (printers.length === 0) {
        console.error('❌ No printers found to test with.');
        return;
    }

    const targetPrinter = printers.find(p => p.isDefault) || printers[0];
    console.log(`-> Target Printer: ${targetPrinter.name}`);

    // 2. Start Monitor
    console.log('\n--- Step 2: Starting Native Monitor ---');
    const monitorSuccess = printerEngine.startPrinterMonitor((event, data) => {
        if (event === 'job') {
            console.log(`\n🔔 MONITOR EVENT: Job #${data.jobId} - ${data.status}`);
            console.log(`   Document: ${data.documentName}`);
            console.log(`   Printer: ${data.printerName}`);
            console.log(`   Pages: ${data.totalPages} | Size: ${data.paperSize} | Color: ${data.color} | Duplex: ${data.duplex}`);
        }
    });

    if (monitorSuccess) {
        console.log('✅ Native Monitor started successfully.');
    } else {
        console.error('❌ Failed to start Native Monitor.');
    }

    // 3. Define Tests
    const testFiles = {
        a4: path.resolve(__dirname, 'blank_A4_4pages.pdf'),
        a3: path.resolve(__dirname, 'blank_A3_4pages.pdf')
    };

    if (!fs.existsSync(testFiles.a4)) console.error(`⚠️ Missing file: ${testFiles.a4}`);
    if (!fs.existsSync(testFiles.a3)) console.error(`⚠️ Missing file: ${testFiles.a3}`);

    const tests = [
        {
            name: 'Test 1: A4, BW, Simplex, 1 Copy',
            file: testFiles.a4,
            options: {
                printer: targetPrinter.name,
                copies: 1,
                pageSize: 'A4',
                colorMode: 'Monochrome',
                duplex: 'None' // or whatever API expects, check win32-printer.js logic if needed, passing strings often requires mapping in JS wrapper if wrapper does mapping. 
                // However, win32-printer.js printJob just passes options to addon.
                // Assuming addon handles string or integer constants. Let's send strings as seen in print-engine.js usage or similar.
                // Re-reading win32-printer.js... it just logs options and calls addon.printJob.
            }
        },
        {
            name: 'Test 2: A3, Color, Duplex, 1 Copy',
            file: testFiles.a3,
            options: {
                printer: targetPrinter.name,
                copies: 1,
                pageSize: 'A3',
                colorMode: 'Color',
                duplex: 'Vertical' // Assuming 'Vertical' maps to standard long-edge
            }
        },
        {
            name: 'Test 3: A4, BW, Duplex, 2 Copies',
            file: testFiles.a4,
            options: {
                printer: targetPrinter.name,
                copies: 2,
                pageSize: 'A4',
                colorMode: 'Monochrome',
                duplex: 'Vertical'
            }
        }
    ];

    // 4. Execute Tests
    console.log('\n--- Step 3: Sending Print Jobs ---');

    for (const test of tests) {
        console.log(`\n▶️ Executing: ${test.name}`);
        if (!fs.existsSync(test.file)) {
            console.log('   ⏭️ Skipping (file missing)');
            continue;
        }

        try {
            const result = await printerEngine.printJob(test.file, test.options);
            if (result.success) {
                console.log(`   ✅ Job Sent with ID: ${result.jobId}`);
            } else {
                console.log(`   ❌ Job Submission Failed: ${result.message}`);
            }
        } catch (e) {
            console.error(`   ❌ Exception during print:`, e.message);
        }

        // Wait a bit between jobs to avoid spamming and let monitor catch up
        await new Promise(r => setTimeout(r, 5000));
    }

    console.log('\n--- Tests Completed. Keeping process alive for 10s to catch final events... ---');
    await new Promise(r => setTimeout(r, 10000));

    printerEngine.stopPrinterMonitor();
    console.log('Monitor stopped. Exiting.');
}

runTests().catch(console.error);
