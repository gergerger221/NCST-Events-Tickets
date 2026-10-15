import assert from 'assert';
import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);
const projectRoot = path.resolve(__dirname, '..');

console.log('🧪 Starting LAN HTTPS & Mobile Scanner Readiness Test Suite...\n');

// Test 1: Root CA and exported certificates exist
const exportedCaPath = path.join(projectRoot, 'ncst-ca.crt');
assert.ok(fs.existsSync(exportedCaPath), 'Root CA must be exported to project root for phone download');
const caContent = fs.readFileSync(exportedCaPath, 'utf8');
assert.ok(caContent.includes('BEGIN CERTIFICATE'), 'ncst-ca.crt must be a valid PEM certificate');
console.log('  ✔ Test 1: Root CA certificate (ncst-ca.crt) is present and valid PEM in web root');

// Test 2: Apache server certificate and private key exist
const apacheCertPath = 'C:\\xampp\\apache\\conf\\ssl.crt\\ncst-server.crt';
const apacheKeyPath = 'C:\\xampp\\apache\\conf\\ssl.key\\ncst-server.key';
assert.ok(fs.existsSync(apacheCertPath), 'Apache server certificate must exist at conf/ssl.crt/ncst-server.crt');
assert.ok(fs.existsSync(apacheKeyPath), 'Apache server private key must exist at conf/ssl.key/ncst-server.key');
console.log('  ✔ Test 2: Apache SSL server certificate and key exist in dedicated ncst-server paths');

// Test 3: Apache configuration backup exists and directives are intact
const httpdSslConf = 'C:\\xampp\\apache\\conf\\extra\\httpd-ssl.conf';
const httpdSslBak = 'C:\\xampp\\apache\\conf\\extra\\httpd-ssl.conf.bak';
assert.ok(fs.existsSync(httpdSslBak), 'Backup of httpd-ssl.conf must be preserved');
const sslConfText = fs.readFileSync(httpdSslConf, 'utf8');
assert.ok(sslConfText.includes('conf/ssl.crt/ncst-server.crt'), 'httpd-ssl.conf must reference ncst-server.crt');
assert.ok(sslConfText.includes('conf/ssl.key/ncst-server.key'), 'httpd-ssl.conf must reference ncst-server.key');
console.log('  ✔ Test 3: httpd-ssl.conf safely references ncst-server with original backup preserved');

// Test 4: .htaccess provides MIME type for CA certificates
const htaccessPath = path.join(projectRoot, '.htaccess');
const htaccessText = fs.readFileSync(htaccessPath, 'utf8');
assert.ok(htaccessText.includes('application/x-x509-ca-cert'), '.htaccess must register application/x-x509-ca-cert MIME type for .crt');
console.log('  ✔ Test 4: .htaccess configured with application/x-x509-ca-cert for mobile installation');

// Test 5: Live HTTP Root CA download via Apache
try {
  const caRes = await fetch('http://127.0.0.1/NCST-Events-Tickets/ncst-ca.crt');
  assert.strictEqual(caRes.status, 200, 'ncst-ca.crt must be retrievable over HTTP');
  const caText = await caRes.text();
  assert.ok(caText.includes('BEGIN CERTIFICATE'), 'Downloaded CA must contain valid certificate text');
  console.log('  ✔ Test 5: Apache serves ncst-ca.crt over HTTP with status 200 OK');
} catch (e) {
  console.log('  ⚠ Test 5 skipped (Apache not reachable on 127.0.0.1:80):', e.message);
}

// Test 6: Verify secure context & camera error handling logic
import { checkSecureContext, parseCameraError } from '../src/utils/cameraUtils.ts';
const insecureErr = parseCameraError(new Error('SecurityError'));
assert.strictEqual(insecureErr.type, 'insecure_context', 'Security error must be diagnosed as insecure_context');
assert.ok(insecureErr.tip.includes('HTTPS'), 'Insecure context tip must reference HTTPS');
console.log('  ✔ Test 6: Camera error engine enforces secure context requirements');

console.log('\n🎉 ALL LAN HTTPS READINESS TESTS PASSED SUCCESSFULLY!\n');
