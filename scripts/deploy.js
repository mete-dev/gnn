import https from 'https';
import fs from 'fs';
import path from 'path';
import { execSync } from 'child_process';
import { fileURLToPath } from 'url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);
const rootDir = path.resolve(__dirname, '..');

// Load environment variables from .env
function loadEnv() {
  const envPath = path.join(rootDir, '.env');
  if (fs.existsSync(envPath)) {
    const content = fs.readFileSync(envPath, 'utf8');
    content.split('\n').forEach(line => {
      const trimmed = line.trim();
      if (trimmed && !trimmed.startsWith('#') && trimmed.includes('=')) {
        const idx = trimmed.indexOf('=');
        const key = trimmed.substring(0, idx).trim();
        const value = trimmed.substring(idx + 1).trim();
        if (!process.env[key]) {
          process.env[key] = value;
        }
      }
    });
  }
}

loadEnv();

const HOST = process.env.CPANEL_HOST || 'karya.kreasi.org';
const USER = process.env.CPANEL_USER || 'goodnews';
const TOKEN = process.env.CPANEL_TOKEN || 'GL15BN4U6TRLQKLL987BAHC0WYSS99VB';

console.log('🚀 Memulai Deployment Otomatis ke cPanel via API...');
console.log(`📌 Server Target: ${HOST} (User: ${USER})`);

function uploadFile(targetDir, localFilePath) {
  return new Promise((resolve, reject) => {
    const fileName = path.basename(localFilePath);
    const boundary = '----WebKitFormBoundary' + Math.random().toString(36).substring(2);
    const fileData = fs.readFileSync(localFilePath);

    let postDataHeader = '';
    postDataHeader += `--${boundary}\r\n`;
    postDataHeader += `Content-Disposition: form-data; name="dir"\r\n\r\n${targetDir}\r\n`;
    postDataHeader += `--${boundary}\r\n`;
    postDataHeader += `Content-Disposition: form-data; name="overwrite"\r\n\r\n1\r\n`;
    postDataHeader += `--${boundary}\r\n`;
    postDataHeader += `Content-Disposition: form-data; name="file-0"; filename="${fileName}"\r\n`;
    postDataHeader += `Content-Type: application/zip\r\n\r\n`;

    const postDataFooter = `\r\n--${boundary}--\r\n`;
    const contentLength = Buffer.byteLength(postDataHeader) + fileData.length + Buffer.byteLength(postDataFooter);

    const options = {
      hostname: HOST,
      port: 2083,
      path: '/execute/Fileman/upload_files',
      method: 'POST',
      headers: {
        'Authorization': `cpanel ${USER}:${TOKEN}`,
        'Content-Type': `multipart/form-data; boundary=${boundary}`,
        'Content-Length': contentLength
      },
      rejectUnauthorized: false
    };

    console.log(`📤 Mengunggah ${fileName} (${(fileData.length / 1024 / 1024).toFixed(2)} MB) ke cPanel (${targetDir})...`);

    const req = https.request(options, (res) => {
      let data = '';
      res.on('data', (chunk) => { data += chunk; });
      res.on('end', () => {
        try {
          const parsed = JSON.parse(data);
          if (parsed.status === 1 || (parsed.data && parsed.data.succeeded > 0)) {
            console.log(`✅ Upload ${fileName} Berhasil!`);
            resolve(parsed);
          } else {
            console.error(`❌ Upload ${fileName} Gagal:`, data);
            reject(new Error(`Upload failed: ${data}`));
          }
        } catch (e) {
          resolve({ raw: data });
        }
      });
    });

    req.on('error', reject);
    req.write(postDataHeader);
    req.write(fileData);
    req.write(postDataFooter);
    req.end();
  });
}

function extractZip(sourceZipFileName, destDir) {
  return new Promise((resolve, reject) => {
    const params = new URLSearchParams({
      cpanel_jsonapi_user: USER,
      cpanel_jsonapi_apiversion: '2',
      cpanel_jsonapi_module: 'Fileman',
      cpanel_jsonapi_func: 'fileop',
      op: 'extract',
      sourcefiles: sourceZipFileName,
      destdir: destDir,
      doublecheck: '1'
    }).toString();

    const options = {
      hostname: HOST,
      port: 2083,
      path: `/json-api/cpanel?${params}`,
      method: 'GET',
      headers: {
        'Authorization': `cpanel ${USER}:${TOKEN}`
      },
      rejectUnauthorized: false
    };

    console.log(`📦 Meng-ekstrak ${sourceZipFileName} ke folder ${destDir}...`);

    const req = https.request(options, (res) => {
      let data = '';
      res.on('data', (chunk) => { data += chunk; });
      res.on('end', () => {
        try {
          const parsed = JSON.parse(data);
          console.log(`✅ Ekstrak ${sourceZipFileName} Berhasil!`);
          resolve(parsed);
        } catch (e) {
          resolve({ raw: data });
        }
      });
    });

    req.on('error', reject);
    req.end();
  });
}

function createDirAPI2(parentPath, folderName) {
  return new Promise((resolve) => {
    const params = new URLSearchParams({
      cpanel_jsonapi_user: USER,
      cpanel_jsonapi_apiversion: '2',
      cpanel_jsonapi_module: 'Fileman',
      cpanel_jsonapi_func: 'mkdir',
      path: parentPath,
      name: folderName
    }).toString();

    const options = {
      hostname: HOST,
      port: 2083,
      path: `/json-api/cpanel?${params}`,
      method: 'GET',
      headers: {
        'Authorization': `cpanel ${USER}:${TOKEN}`
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

    req.on('error', () => resolve(null));
    req.end();
  });
}

async function main() {
  try {
    console.log('\n📁 Memastikan struktur folder cPanel tersedia...');
    await createDirAPI2('/home/goodnews', 'public_html');
    await createDirAPI2('/home/goodnews', 'backend_goodnews');
    await createDirAPI2('/home/goodnews/backend_goodnews', 'tmp');
    // Step 1: Build production
    console.log('\n1️⃣ Membangun aplikasi frontend & backend...');
    execSync('npm run build', { cwd: rootDir, stdio: 'inherit' });
    console.log('⚡ Mengompilasi server.ts ke server.js...');
    execSync('npx esbuild server.ts --bundle --platform=node --format=esm --outfile=server.js --external:express --external:@google/genai --external:dotenv --external:vite', { cwd: rootDir, stdio: 'inherit' });

    // Step 2: Zip production packages
    console.log('\n2️⃣ Membuahkan arsip zip distribusi...');
    const zipCmd = `powershell -Command "Compress-Archive -Path dist\\* -DestinationPath frontend_public_html.zip -Force; Compress-Archive -Path server.js, server.ts, package.json, package-lock.json, gnn_news_db.json, gnn_users_db.json, gnn_gallery_db.json, gnn_videos_db.json, src -DestinationPath backend_goodnews.zip -Force"`;
    execSync(zipCmd, { cwd: rootDir, stdio: 'inherit' });

    // Step 3: Upload Frontend
    const frontendZipPath = path.join(rootDir, 'frontend_public_html.zip');
    await uploadFile('/home/goodnews', frontendZipPath);
    await extractZip('frontend_public_html.zip', '/home/goodnews/public_html');

    // Step 4: Upload Backend
    const backendZipPath = path.join(rootDir, 'backend_goodnews.zip');
    await uploadFile('/home/goodnews', backendZipPath);
    await extractZip('backend_goodnews.zip', '/home/goodnews/backend_goodnews');

    // Step 5: Touch tmp/restart.txt to restart Node.js server
    console.log('\n🔄 Me-restart Server Node.js cPanel...');
    const restartFile = path.join(rootDir, 'scripts', 'restart.txt');
    fs.mkdirSync(path.join(rootDir, 'scripts'), { recursive: true });
    fs.writeFileSync(restartFile, `Restarted at ${new Date().toISOString()}`);
    await uploadFile('/home/goodnews/backend_goodnews/tmp', restartFile);

    console.log('\n🎉 ================================================');
    console.log('🎉 DEPLOYMENT BERHASIL TOTAL VIA CPANEL API!');
    console.log('🎉 Web frontend & backend telah terupdate otomatis.');
    console.log('🎉 Domain: https://www.goodnewsnusantara.my.id');
    console.log('🎉 ================================================\n');
  } catch (err) {
    console.error('\n❌ Terjadi kesalahan saat deployment:', err.message);
    process.exit(1);
  }
}

main();
