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

async function checkHomeDir() {
  console.log('Listing files in /home/goodnews:');
  const res = await callUAPI('Fileman', 'list_files', { dir: '/home/goodnews' });
  if (res.data) {
    console.log(res.data.map(f => `${f.file} (${f.type}, ${f.humansize})`).join('\n'));
  } else {
    console.log('Error/No Data:', JSON.stringify(res));
  }
}

checkHomeDir();
