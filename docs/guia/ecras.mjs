// Fotografa os ecrãs do backoffice para o guia em PDF.
// Uso: node guia-ecras.mjs <pasta-saida> <base-url> <slug-do-imovel>
import { spawn } from 'node:child_process';
import { writeFileSync, mkdirSync } from 'node:fs';
import { join } from 'node:path';
const EDGE = 'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe';
const PORT = 9410, OUT = process.argv[2], BASE = process.argv[3], SLUG = process.argv[4];
mkdirSync(OUT, { recursive: true });
const edge = spawn(EDGE, [`--remote-debugging-port=${PORT}`, '--headless=new', '--disable-gpu', '--ignore-certificate-errors', '--window-size=1440,900', '--user-data-dir=' + join(OUT, 'p'), 'about:blank'], { stdio: 'ignore' });
const sleep = (ms) => new Promise(r => setTimeout(r, ms));
let t; for (let i = 0; i < 40 && !t; i++) { await sleep(500); try { t = (await (await fetch(`http://127.0.0.1:${PORT}/json/list`)).json()).find(x => x.type === 'page'); } catch {} }
const ws = new WebSocket(t.webSocketDebuggerUrl); await new Promise(r => ws.onopen = r);
let id = 0; const p = new Map();
ws.onmessage = (m) => { const d = JSON.parse(m.data); if (d.id && p.has(d.id)) { p.get(d.id)(d); p.delete(d.id); } };
const send = (mm, pp = {}) => new Promise(r => { const i = ++id; p.set(i, r); ws.send(JSON.stringify({ id: i, method: mm, params: pp })); });
const ev = async (e) => { const r = await send('Runtime.evaluate', { expression: e, returnByValue: true, awaitPromise: true }); return r.result.exceptionDetails ? ('ERRO ' + (r.result.exceptionDetails.exception?.description || '').slice(0, 100)) : r.result.result.value; };
await send('Page.enable'); await send('Runtime.enable');
// Modo claro sempre, para as imagens serem coerentes.
await send('Emulation.setEmulatedMedia', { features: [{ name: 'prefers-color-scheme', value: 'light' }] });
const viewport = async (h) => send('Emulation.setDeviceMetricsOverride', { width: 1440, height: h, deviceScaleFactor: 1, mobile: false });
await viewport(900);

const feitos = [];
const shot = async (nome, alturaMax = 900) => {
  await ev(`document.documentElement.classList.remove('dark'); localStorage.setItem('theme','light'); localStorage.setItem('isOpen','true')`);
  await sleep(300);
  const total = Math.min(await ev(`document.documentElement.scrollHeight`), alturaMax);
  await viewport(total);
  await sleep(500);
  const x = await send('Page.captureScreenshot', { format: 'jpeg', quality: 82, captureBeyondViewport: true });
  writeFileSync(join(OUT, nome + '.jpg'), Buffer.from(x.result.data, 'base64'));
  feitos.push(nome + ' ' + total);
  await viewport(900);
};
const ir = async (url, espera = 3500) => { await send('Page.navigate', { url }); await sleep(espera); };
const clicarTexto = async (rx) => ev(`(() => { const b = [...document.querySelectorAll('button, a, [role=tab]')].find(x => ${rx}.test(x.textContent.trim())); if (!b) return 'nao encontrei'; b.click(); return 'ok'; })()`);

// 1. Entrar
await ir(BASE + '/entrar');
await ev(`(() => { const e = document.querySelector('input[type=email]'); e.value = 'ana.silva@multifuturo.pt'; e.dispatchEvent(new Event('input',{bubbles:true})); })()`);
await shot('01-entrar', 900);
await ev(`(() => { const e = document.querySelector('input[type=email]'); const pw = document.querySelector('input[type=password]'); e.value='suporte@nxs.pt'; e.dispatchEvent(new Event('input',{bubbles:true})); pw.value='Nexus!2026'; pw.dispatchEvent(new Event('input',{bubbles:true})); document.querySelector('form').submit(); })()`);
await sleep(4000);

// 2. Portal
await ir(BASE + '/portal');
await shot('02-portal', 900);

// 3. Painel
await ir(BASE + '/admin', 6000);
await ev(`[...document.querySelectorAll('button')].find(b => /Aceitar tudo/i.test(b.textContent))?.click()`);
await shot('03-painel', 1500);

// 4. Lista de imóveis
await ir(BASE + '/admin/properties', 5000);
await shot('04-imoveis-lista', 1100);

// 5..10 Ficha, separador a separador
await ir(`${BASE}/admin/properties/${SLUG}/edit`, 5000);
const separadores = [['Geral', /^Geral$/], ['Interna', /^Interna/], ['Localiza', /^Localiza/], ['Media', /^Media$/], ['Detalhes', /^Detalhes$/], ['Descri', /^Descri/]];
let n = 5;
for (const [nome, rx] of separadores) {
  await clicarTexto(rx.toString());
  await sleep(1500);
  await shot(`${String(n).padStart(2, '0')}-ficha-${nome.toLowerCase()}`, 1500);
  n++;
}
// O bloco inglês com o botão de tradução
await ev(`[...document.querySelectorAll('button, h3, [role=button]')].filter(b => /^English$/i.test(b.textContent.trim()))[0]?.click()`);
await sleep(1000);
await ev(`[...document.querySelectorAll('button, a')].find(b => /Traduzir do/i.test(b.textContent))?.scrollIntoView({block:'center'})`);
await sleep(600);
{
  const x = await send('Page.captureScreenshot', { format: 'jpeg', quality: 82 });
  writeFileSync(join(OUT, '11-ficha-traduzir.jpg'), Buffer.from(x.result.data, 'base64'));
  feitos.push('11-ficha-traduzir');
}

// 12. Dúvidas dos clientes (lista e detalhe)
await ir(BASE + '/admin/leads', 5000);
await shot('12-duvidas-lista', 1000);
await ir(BASE + '/admin/leads/1', 5000);
await shot('13-duvidas-detalhe', 1500);

// 14. Clientes
await ir(BASE + '/admin/contacts', 5000);
await shot('14-clientes', 1000);

// 15. Agenda
await ir(BASE + '/admin/events', 5000);
await shot('15-agenda', 1000);

// 16. Calendário
await ir(BASE + '/admin/calendario', 6000);
await shot('16-calendario', 1300);

// 17. Equipa
await ir(BASE + '/gestao/equipa', 4000);
await shot('17-equipa', 1000);

// 18. A minha conta
await ir(BASE + '/conta', 4000);
await shot('18-conta', 1000);

console.log(feitos.join('\n'));
ws.close(); edge.kill();
