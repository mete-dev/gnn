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

async function checkDomains2() {
  console.log('DomainInfo::domains_data:');
  const res = await callUAPI('DomainInfo', 'domains_data');
  console.log(JSON.stringify(res, null, 2));
}

checkDomains2();
