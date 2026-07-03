const https = require('https');
https.get('https://github.com/muarf/dupli-electron-caddy/releases.atom', (res) => {
    let data = '';
    res.on('data', d => data += d);
    res.on('end', () => {
        const tags = [...data.matchAll(/releases\/tag\/([^"<\/]+)/g)].map(m => m[1]);
        console.log('Feed top 10 tags:');
        tags.slice(0, 10).forEach(t => console.log(' -', t));
        const hasBeta = tags.some(t => t.includes('beta'));
        console.log('Has beta in feed:', hasBeta);
    });
});
