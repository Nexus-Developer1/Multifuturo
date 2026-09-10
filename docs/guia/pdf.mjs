// Monta o guia em HTML a partir do Markdown e das fotografias, e imprime-o em PDF.
// Uso: node guia-pdf.mjs <GUIA.md> <pasta-das-imagens> <saida.pdf>
import { readFileSync, writeFileSync, existsSync, readdirSync } from 'node:fs';
import { spawnSync } from 'node:child_process';
import { join, resolve, dirname } from 'node:path';

const [md, pasta, pdf] = process.argv.slice(2).map(x => resolve(x));
const EDGE = 'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe';

// Que imagem entra depois de que título (o título tal como está no Markdown).
const imagens = {
  '## 1. Entrar': [['01-entrar', 'O ecrã de entrada.']],
  '## 2. O Portal': [['02-portal', 'O Portal, com os módulos a que a conta tem acesso.']],
  '## 3. O Painel de Controlo': [['03-painel', 'O Painel de Controlo.']],
  '### 4.1 Encontrar um imóvel': [['04-imoveis-lista', 'A lista de imóveis, com a pesquisa, os filtros e os interruptores.']],
  '### 4.3 Criar ou editar uma ficha': [
    ['05-ficha-geral', 'Separador Geral: estado, referência, tipo, áreas, preço e visibilidade.'],
    ['06-ficha-interna', 'Separador Interna: o que é só da agência e nunca aparece no site.'],
    ['07-ficha-localiza', 'Separador Localização: morada e mapa.'],
    ['08-ficha-media', 'Separador Media: fotografias, documentos e ligações.'],
    ['09-ficha-detalhes', 'Separador Detalhes: características.'],
    ['10-ficha-descri', 'Separador Descrições: os textos do anúncio, por idioma.'],
  ],
  '### 4.4 Traduzir para inglês': [['11-ficha-traduzir', 'O botão "Traduzir do português", na barra do bloco English.']],
  '## 5. Dúvidas dos clientes': [
    ['12-duvidas-lista', 'A caixa de entrada dos pedidos.'],
    ['13-duvidas-detalhe', 'Um pedido aberto, com os botões para responder.'],
  ],
  '## 6. Clientes': [['14-clientes', 'A lista de clientes.']],
  '## 7. Agenda': [['15-agenda', 'A agenda.']],
  '## 8. Calendário': [['16-calendario', 'O calendário.']],
  '## 9. Equipa e acessos (só administradores)': [['17-equipa', 'Equipa e acessos, no Portal.']],
  '## 10. A minha conta': [['18-conta', 'A minha conta.']],
};

const esc = (s) => s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
const inline = (s) => esc(s)
  .replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>')
  .replace(/\*(.+?)\*/g, '<em>$1</em>')
  .replace(/`([^`]+)`/g, '<code>$1</code>');

const figura = ([ficheiro, legenda]) => {
  const caminho = join(pasta, ficheiro + '.jpg');
  if (!existsSync(caminho)) return `<p class="falta">[imagem em falta: ${ficheiro}]</p>`;
  const dados = readFileSync(caminho).toString('base64');
  return `<figure><img src="data:image/jpeg;base64,${dados}" alt="${esc(legenda)}"><figcaption>${esc(legenda)}</figcaption></figure>`;
};

const linhas = readFileSync(md, 'utf8').split(/\r?\n/);
let html = '', i = 0, titulo = '';
const paragrafo = [];
const fecharParagrafo = () => { if (paragrafo.length) { html += `<p>${inline(paragrafo.join(' '))}</p>\n`; paragrafo.length = 0; } };

while (i < linhas.length) {
  const l = linhas[i];
  if (/^# /.test(l)) { fecharParagrafo(); titulo = l.slice(2); html += `<h1>${inline(titulo)}</h1>\n`; i++; continue; }
  if (/^##+ /.test(l)) {
    fecharParagrafo();
    const nivel = l.match(/^(#+)/)[1].length;
    const texto = l.replace(/^#+ /, '');
    html += `<h${nivel}${nivel === 2 ? ' class="seccao"' : ''}>${inline(texto)}</h${nivel}>\n`;
    for (const f of imagens[l.trim()] ?? []) html += figura(f) + '\n';
    i++; continue;
  }
  if (/^---\s*$/.test(l)) { fecharParagrafo(); i++; continue; }
  if (/^\|/.test(l)) {
    fecharParagrafo();
    const filas = [];
    while (i < linhas.length && /^\|/.test(linhas[i])) { filas.push(linhas[i]); i++; }
    const celulas = (f) => f.replace(/^\||\|$/g, '').split('|').map(c => inline(c.trim()));
    const cab = celulas(filas[0]);
    const corpo = filas.slice(2).map(celulas);
    html += '<table><thead><tr>' + cab.map(c => `<th>${c}</th>`).join('') + '</tr></thead><tbody>'
      + corpo.map(r => '<tr>' + r.map(c => `<td>${c}</td>`).join('') + '</tr>').join('') + '</tbody></table>\n';
    continue;
  }
  if (/^\s*(\d+\.|[-*]) /.test(l)) {
    fecharParagrafo();
    const ordenada = /^\s*\d+\./.test(l);
    const itens = [];
    while (i < linhas.length && (/^\s*(\d+\.|[-*]) /.test(linhas[i]) || /^\s{2,}\S/.test(linhas[i]))) {
      if (/^\s*(\d+\.|[-*]) /.test(linhas[i])) itens.push(linhas[i].replace(/^\s*(\d+\.|[-*]) /, ''));
      else itens[itens.length - 1] += ' ' + linhas[i].trim();
      i++;
    }
    html += `<${ordenada ? 'ol' : 'ul'}>` + itens.map(x => `<li>${inline(x)}</li>`).join('') + `</${ordenada ? 'ol' : 'ul'}>\n`;
    continue;
  }
  if (l.trim() === '') { fecharParagrafo(); i++; continue; }
  paragrafo.push(l.trim()); i++;
}
fecharParagrafo();

const logo = (() => {
  const c = resolve(dirname(md), '..', 'public', 'images', 'marca', 'logotipo.png');
  return existsSync(c) ? `<img class="logo" src="data:image/png;base64,${readFileSync(c).toString('base64')}" alt="">` : '';
})();

const hoje = new Date().toLocaleDateString('pt-PT', { day: 'numeric', month: 'long', year: 'numeric' });
const pagina = `<!doctype html><html lang="pt"><head><meta charset="utf-8"><title>${esc(titulo)}</title>
<style>
  @page { size: A4; margin: 18mm 16mm 20mm 16mm; }
  body { font-family: "Segoe UI", Inter, Arial, sans-serif; color: #26261F; font-size: 10.5pt; line-height: 1.5; }
  .capa { height: 245mm; display: flex; flex-direction: column; justify-content: center; page-break-after: always; }
  .capa .logo { width: 42mm; margin-bottom: 14mm; }
  .capa h1 { font-size: 30pt; line-height: 1.1; margin: 0 0 6mm; font-weight: 600; }
  .capa p { color: #6F6C60; font-size: 12pt; margin: 0 0 2mm; }
  h1 { font-size: 22pt; margin: 0 0 8mm; }
  h2.seccao { font-size: 16pt; margin: 0 0 4mm; padding-top: 3mm; border-top: 2px solid #5D6348; color: #2F3320; page-break-before: always; page-break-after: avoid; }
  h3 { font-size: 12.5pt; margin: 6mm 0 2mm; color: #4A4F39; page-break-after: avoid; }
  p { margin: 0 0 3mm; }
  ul, ol { margin: 0 0 3mm; padding-left: 6mm; }
  li { margin-bottom: 1.2mm; }
  code { font-family: Consolas, monospace; font-size: 9.5pt; background: #F2EDE1; padding: 0 1mm; border-radius: 2px; }
  table { border-collapse: collapse; width: 100%; margin: 2mm 0 4mm; font-size: 9.8pt; page-break-inside: avoid; }
  th, td { border: 1px solid #E5DCC9; padding: 1.6mm 2.4mm; text-align: left; vertical-align: top; }
  th { background: #F2EDE1; font-weight: 600; }
  figure { margin: 3mm 0 5mm; page-break-inside: avoid; }
  figure img { width: 100%; border: 1px solid #E5DCC9; border-radius: 3px; }
  figcaption { font-size: 9pt; color: #6F6C60; margin-top: 1.5mm; }
  .falta { color: #B00; }
  .intro { page-break-after: always; }
</style></head><body>
<div class="capa">${logo}<h1>Guia do backoffice</h1><p>Multifuturo Propriedades</p><p>Como funciona, ecrã a ecrã</p><p>${hoje}</p></div>
${html.replace('<h1>', '<div class="intro"><h1>').replace('<h2 class="seccao">', '</div><h2 class="seccao">')}
</body></html>`;

const htmlPath = pdf.replace(/\.pdf$/i, '.html');
writeFileSync(htmlPath, pagina, 'utf8');
// Perfil próprio: sem ele o Edge entrega o pedido a uma janela já aberta do utilizador e sai sem imprimir.
const perfil = join(dirname(htmlPath), 'perfil-impressao');
const r = spawnSync(EDGE, ['--headless', '--disable-gpu', '--user-data-dir=' + perfil, '--no-pdf-header-footer', '--print-to-pdf=' + pdf, '--virtual-time-budget=10000', 'file:///' + htmlPath.replace(/\\/g, '/')], { encoding: 'utf8', timeout: 180000 });
console.log('pdf:', existsSync(pdf) ? pdf : 'NAO GERADO ' + (r.stderr || '').slice(-300));
console.log('imagens disponiveis:', readdirSync(pasta).filter(f => f.endsWith('.jpg')).length);
