import https from 'https';

const host = 'karya.kreasi.org';
const user = 'goodnews';
const token = 'GL15BN4U6TRLQKLL987BAHC0WYSS99VB';

function callAPI(pathStr) {
  return new Promise((resolve, reject) => {
    const options = {
      hostname: host,
      port: 2083,
      path: pathStr,
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

async function makeDirsAPI2() {
  console.log('1. Creating public_html via API2...');
  const r1 = await callAPI(`/json-api/cpanel?cpanel_jsonapi_user=${user}&cpanel_jsonapi_apiversion=2&cpanel_jsonapi_module=Fileman&cpanel_jsonapi_func=mkdir&path=%2Fhome%2Fgoodnews&name=public_html`);
  console.log('Result 1:', JSON.stringify(r1, null, 2));

  console.log('2. Creating backend_goodnews via API2...');
  const r2 = await callAPI(`/json-api/cpanel?cpanel_jsonapi_user=${user}&cpanel_jsonapi_apiversion=2&cpanel_jsonapi_module=Fileman&cpanel_jsonapi_func=mkdir&path=%2Fhome%2Fgoodnews&name=backend_goodnews`);
  console.log('Result 2:', JSON.stringify(r2, null, 2));

  console.log('3. Creating tmp inside backend_goodnews via API2...');
  const r3 = await callAPI(`/json-api/cpanel?cpanel_jsonapi_user=${user}&cpanel_jsonapi_apiversion=2&cpanel_jsonapi_module=Fileman&cpanel_jsonapi_func=mkdir&path=%2Fhome%2Fgoodnews%2Fbackend_goodnews&name=tmp`);
  console.log('Result 3:', JSON.stringify(r3, null, 2));
}

makeDirsAPI2();
