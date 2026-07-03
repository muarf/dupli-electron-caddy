const { app } = require('electron');
const { autoUpdater } = require('electron-updater');autoUpdater.autoDownload = false;
autoUpdater.allowPrerelease = true;
autoUpdater.forceDevUpdateConfig = true;
autoUpdater.channel = 'beta';
autoUpdater.logger = console;

autoUpdater.setFeedURL({
    provider: 'github',
    owner: 'muarf',
    repo: 'dupli-electron-caddy',
    channel: 'beta',
    releaseType: 'prerelease'
});

autoUpdater.on('error', (err) => {
    console.error('Updater Error:', err);
});

app.whenReady().then(() => {
    autoUpdater.checkForUpdates().then((res) => {
        console.log('Result:', res);
        app.quit();
    }).catch((err) => {
        console.error('Check failed:', err);
        app.quit();
    });
});
