import https from 'https';

const host = 'karya.kreasi.org';
const user = 'goodnews';
const token = 'GL15BN4U6TRLQKLL987BAHC0WYSS99VB';

function callUAPI(module, fn, params = {}) {
  return new Promise((resolve, reject) => {
    const query = new URLSearchParams(params).toString();
    const options = {
      hostname: host,
      port: 2083,
      path: `/execute/${module}/${fn}?${query}`,
      method: 'GET',
      headers: {
        'Authorization': `cpanel ${user}:${token}`
      },
      rejectUnauthorized: false
    };

    const req = https.request(options, (res) => {
      let data = '';
      res.on('data', (chunk) => { data += chunk; });
      res.on('end', () => {
        try {
          resolve(JSON.parse(data));
        } catch (e) {
          resolve({ raw: data });
        }
      });
    });

    req.on('error', reject);
    req.end();
  });
}

async function makeDirs() {
  console.log('1. Creating public_html...');
  const res1 = await callUAPI('Fileman', 'mkdir', { path: '/home/goodnews', name: 'public_html' });
  console.log('mkdir public_html:', JSON.stringify(res1));

  console.log('2. Creating backend_goodnews...');
  const res2 = await callUAPI('Fileman', 'mkdir', { path: '/home/goodnews', name: 'backend_goodnews' });
  console.log('mkdir backend_goodnews:', JSON.stringify(res2));

  console.log('3. Creating backend_goodnews/tmp...');
  const res3 = await callUAPI('Fileman', 'mkdir', { path: '/home/goodnews/backend_goodnews', name: 'tmp' });
  console.log('mkdir tmp:', JSON.stringify(res3));
}

makeDirs();
