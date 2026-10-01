import https from 'https';

function checkLiveUrl(url, method = 'GET', body = null) {
  return new Promise((resolve, reject) => {
    const parsed = new URL(url);
    const postData = body ? JSON.stringify(body) : null;
    const options = {
      hostname: parsed.hostname,
      port: 443,
      path: parsed.pathname + parsed.search,
      method: method,
      headers: {
        'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',
        ...(postData ? {
          'Content-Type': 'application/json',
          'Content-Length': Buffer.byteLength(postData)
        } : {})
      },
      rejectUnauthorized: false
    };

    const req = https.request(options, (res) => {
      let data = '';
      console.log(`[${method} ${url}] Status: ${res.statusCode}, Content-Type: ${res.headers['content-type']}`);
      res.on('data', (chunk) => { data += chunk; });
      res.on('end', () => {
        resolve({ status: res.statusCode, headers: res.headers, data: data.substring(0, 500) });
      });
    });

    req.on('error', reject);
    if (postData) req.write(postData);
    req.end();
  });
}

async function testSite() {
  console.log('1. Checking home page...');
  await checkLiveUrl('https://www.goodnewsnusantara.my.id/');

  console.log('\n2. Checking POST /api/auth/login...');
  await checkLiveUrl('https://www.goodnewsnusantara.my.id/api/auth/login', 'POST', {
    identifier: 'admin',
    password: 'admin'
  });
}

testSite();
