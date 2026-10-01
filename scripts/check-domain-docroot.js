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

async function checkDomains() {
  console.log('1. Checking DomainUserData::default_domain...');
  const res1 = await callUAPI('DomainUserData', 'default_domain');
  console.log('Default Domain Data:', JSON.stringify(res1, null, 2));

  console.log('\n2. Checking DomainUserData::user_domains...');
  const res2 = await callUAPI('DomainUserData', 'user_domains');
  console.log('User Domains Data:', JSON.stringify(res2, null, 2));
}

checkDomains();
