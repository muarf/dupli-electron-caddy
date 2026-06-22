const { app } = require('electron');
const path = require('path');
const fs = require('fs');

// Mock app if needed or just use electron
try {
    const userDataPath = app.getPath('userData');
    console.log('UserData Path:', userDataPath);
} catch (e) {
    console.log('Error:', e.message);
}
