// Ponto de entrada JS. O Alpine é injetado pelo Livewire 3 — não o importar aqui.
// Sem tracking, sem chamadas externas: qualquer script de terceiros fica atrás
// do consentimento de cookies (Fase 6).
import './bootstrap';
import './consent';
import './motion';

/*
 * Favoritos em localStorage — sem registo, sem servidor. Guardamos só os slugs;
 * a página /favoritos pede ao servidor os cartões desses slugs.
 */
const FAVORITES_KEY = 'multifuturo:favoritos';
const COMPARE_KEY = 'multifuturo:comparar';
const COMPARE_MAX = 3;

/*
 * Pesquisa com sugestões: enquanto se escreve, o servidor devolve concelhos,
 * freguesias e imóveis que correspondem (SearchSuggestController). Teclado
 * completo (setas, Enter, Escape) e sem JavaScript o formulário submete-se
 * na mesma. Nada é guardado — é só leitura da carteira publicada.
 */
function suggestions(endpoint) {
    return {
        open: false,
        items: [],
        active: -1,
        loading: false,
        timer: null,
        controller: null,

        onInput(value) {
            const q = value.trim();
            this.active = -1;
            clearTimeout(this.timer);

            if (q.length < 2) {
                this.items = [];
                this.open = false;
                return;
            }

            this.timer = setTimeout(() => this.fetch(q), 220);
        },

        async fetch(q) {
            this.controller?.abort();
            this.controller = new AbortController();
            this.loading = true;

            try {
                const url = endpoint + '?q=' + encodeURIComponent(q) + '&f=' + encodeURIComponent(this.$refs.tipo?.value === 'rent' ? 'rent' : 'buy');
                const response = await fetch(url, { headers: { Accept: 'application/json' }, signal: this.controller.signal });
                if (!response.ok) return;
                this.items = (await response.json()).items ?? [];
                this.open = true;
            } catch {
                /* pedido cancelado ou rede em baixo: o formulário continua a funcionar */
            } finally {
                this.loading = false;
            }
        },

        move(delta) {
            if (!this.open || !this.items.length) return;
            this.active = (this.active + delta + this.items.length) % this.items.length;
        },

        /** Enter: se houver sugestão escolhida vai-se lá; senão submete a pesquisa. */
        choose(event) {
            if (this.open && this.active >= 0 && this.items[this.active]) {
                event.preventDefault();
                window.location.href = this.items[this.active].url;
            }
        },

        close() {
            this.open = false;
            this.active = -1;
        },
    };
}

/*
 * Leaflet a pedido: os ficheiros vivem no nosso servidor (public/vendor/leaflet)
 * e só são carregados quando há mesmo um mapa para desenhar. Uma promessa
 * partilhada evita carregá-los duas vezes na mesma página.
 */
let leafletPromise = null;

function loadLeaflet(assets) {
    if (window.L) return Promise.resolve();
    if (leafletPromise) return leafletPromise;

    leafletPromise = new Promise((resolve, reject) => {
        const css = document.createElement('link');
        css.rel = 'stylesheet';
        css.href = assets.css;
        document.head.appendChild(css);

        const js = document.createElement('script');
        js.src = assets.js;
        js.onload = resolve;
        js.onerror = reject;
        document.head.appendChild(js);
    });

    return leafletPromise;
}

/** O alfinete da marca, com as imagens do nosso storage. */
function marker(assets) {
    return window.L.icon({
        iconUrl: assets.icon,
        iconRetinaUrl: assets.icon2x,
        shadowUrl: assets.shadow,
        iconSize: [25, 41],
        iconAnchor: [12, 41],
        shadowSize: [41, 41],
    });
}

/** Atribuição mínima que a licença do OpenStreetMap exige. */
function attribution(map) {
    const link = '<a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener">OpenStreetMap</a>';
    window.L.control.attribution({ prefix: false }).addAttribution('&copy; ' + link).addTo(map);
}

/*
 * Mapa da ficha de imóvel: um alfinete, desenhado assim que a página abre.
 * Só existe quando o proprietário autorizou mostrar a localização.
 */
function propertyMap(assets, lat, lon) {
    return {
        async init() {
            await loadLeaflet(assets);
            if (this.map) return;
            const pos = [lat, lon];
            this.map = window.L.map(this.$refs.map, { scrollWheelZoom: false, attributionControl: false }).setView(pos, 15);
            window.L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19 }).addTo(this.map);
            attribution(this.map);
            window.L.marker(pos, { icon: marker(assets) }).addTo(this.map);
        },
        map: null,
    };
}

/*
 * Fotografias da abertura a alternar. Troca de imagem de X em X tempo, com um
 * esbatimento lento; os pontos deixam escolher à mão e param a rotação (quem
 * escolhe manda). Com "reduzir movimento" ligado, fica na primeira e quieta.
 */
function slideshow(total, intervalo = 5000) {
    return {
        atual: 0,
        total,
        timer: null,

        init() {
            if (this.total < 2) return;
            if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
            this.comecar();
            // Numa página em segundo plano não vale a pena andar a trocar imagens.
            document.addEventListener('visibilitychange', () => {
                document.hidden ? this.parar() : this.comecar();
            });
        },

        comecar() {
            this.parar();
            this.timer = setInterval(() => { this.atual = (this.atual + 1) % this.total; }, intervalo);
        },

        parar() {
            if (this.timer) clearInterval(this.timer);
            this.timer = null;
        },

        /** Escolha manual: mostra a imagem e deixa de rodar sozinha. */
        ir(i) {
            this.atual = i;
            this.parar();
        },
    };
}

/*
 * Lista de opções desenhada por nós.
 *
 * A lista que o browser abre num <select> é desenhada pelo sistema operativo:
 * o CSS não lhe chega. Para ter o desenho do site, o <select> real continua na
 * página (é ele que guarda o valor, alimenta o Livewire e funciona sem
 * JavaScript) e, quando o Alpine arranca, esconde-se e passa a ser conduzido
 * por um botão e uma lista nossos. Se o Alpine não arrancar, fica o <select>
 * de sempre — ninguém fica sem filtro.
 */
function listbox() {
    return {
        pronto: false,
        aberto: false,
        activo: -1,
        // Cópia reactiva do <select>: sem ela, o rótulo do botão não mudava ao escolher.
        indice: 0,

        init() {
            this.pronto = true;
            this.sincronizar();
            this.nativo.addEventListener('change', () => this.sincronizar());
            // O Livewire mexe no <select> por baixo (limpar filtros, opções que mudam
            // com o concelho): o observador mantém o rótulo certo sem depender de
            // eventos que o morph não dispara.
            new MutationObserver(() => this.sincronizar())
                .observe(this.nativo, { attributes: true, childList: true, subtree: true });
        },

        sincronizar() {
            this.indice = this.nativo.selectedIndex;
        },

        get nativo() {
            return this.$refs.nativo;
        },

        get opcoes() {
            return [...this.nativo.options];
        },

        get rotulo() {
            return this.nativo.options[this.indice]?.text ?? '';
        },

        abrir() {
            if (this.nativo.disabled) return;
            this.sincronizar();
            this.aberto = true;
            this.activo = this.indice;
            // A opção escolhida entra no ecrã antes de a pessoa procurar por ela.
            this.$nextTick(() => this.$refs.lista?.children[this.activo]?.scrollIntoView({ block: 'nearest' }));
        },

        fechar() {
            this.aberto = false;
            this.activo = -1;
        },

        alternar() {
            this.aberto ? this.fechar() : this.abrir();
        },

        mover(passo) {
            if (!this.aberto) return this.abrir();
            const total = this.opcoes.length;
            this.activo = (this.activo + passo + total) % total;
            this.$refs.lista?.children[this.activo]?.scrollIntoView({ block: 'nearest' });
        },

        /** Escolher escreve no <select> real e avisa o Livewire, como faria um clique. */
        escolher(i) {
            this.nativo.selectedIndex = i;
            this.sincronizar();
            this.nativo.dispatchEvent(new Event('input', { bubbles: true }));
            this.nativo.dispatchEvent(new Event('change', { bubbles: true }));
            this.fechar();
            this.$refs.botao?.focus();
        },

        confirmar() {
            if (this.aberto && this.activo >= 0) this.escolher(this.activo);
            else this.alternar();
        },
    };
}

/*
 * O Livewire pode mudar o valor de um <select> sem disparar 'change' nem mexer
 * no HTML (é o caso do "limpar filtros"): as nossas listas ficavam com o rótulo
 * antigo. O aviso sai no fim da troca do DOM, mas só na volta seguinte do
 * relógio: o Livewire ainda repõe o valor dos campos depois do 'morphed'.
 */
document.addEventListener('livewire:init', () => {
    window.Livewire.hook('morphed', () => {
        setTimeout(() => window.dispatchEvent(new CustomEvent('livewire-atualizado')), 0);
    });
});

document.addEventListener('alpine:init', () => {
    window.Alpine.data('listbox', listbox);

    window.Alpine.data('slideshow', slideshow);
    window.Alpine.data('suggestions', suggestions);
    window.Alpine.data('propertyMap', propertyMap);

    window.Alpine.store('favorites', {
        slugs: [],

        init() {
            try {
                const raw = window.localStorage.getItem(FAVORITES_KEY);
                this.slugs = raw ? JSON.parse(raw).filter((s) => typeof s === 'string') : [];
            } catch {
                this.slugs = [];
            }
        },

        has(slug) {
            return this.slugs.includes(slug);
        },

        toggle(slug) {
            this.slugs = this.has(slug) ? this.slugs.filter((s) => s !== slug) : [...this.slugs, slug];
            this.persist();
        },

        remove(slug) {
            this.slugs = this.slugs.filter((s) => s !== slug);
            this.persist();
        },

        /*
         * Poda os favoritos que já não existem no site (imóveis vendidos,
         * retirados ou apagados). Sem isto, um slug morto ficava preso no
         * localStorage para sempre: o coração contava-o e ele nunca saía.
         * Chamado pela página de favoritos com a lista que o servidor devolveu.
         */
        prune(valid) {
            const keep = this.slugs.filter((s) => valid.includes(s));
            if (keep.length !== this.slugs.length) {
                this.slugs = keep;
                this.persist();
            }
        },

        get count() {
            return this.slugs.length;
        },

        persist() {
            try {
                window.localStorage.setItem(FAVORITES_KEY, JSON.stringify(this.slugs));
            } catch {
                /* armazenamento indisponível (modo privado) — os favoritos vivem só nesta sessão */
            }
        },
    });

    /*
     * Comparador: até três imóveis escolhidos, guardados no aparelho do
     * visitante. A página /comparar lê-os e pede ao servidor os dados para a
     * tabela; nada disto sai daqui sem ser por escolha de quem navega.
     */
    window.Alpine.store('compare', {
        slugs: [],
        max: COMPARE_MAX,
        full: false,

        init() {
            try {
                const raw = window.localStorage.getItem(COMPARE_KEY);
                this.slugs = raw ? JSON.parse(raw).filter((s) => typeof s === 'string').slice(0, COMPARE_MAX) : [];
            } catch {
                this.slugs = [];
            }
        },

        has(slug) {
            return this.slugs.includes(slug);
        },

        /** Tirar funciona sempre; pôr só até ao limite — daí o aviso "full". */
        toggle(slug) {
            if (this.has(slug)) {
                this.slugs = this.slugs.filter((s) => s !== slug);
            } else {
                if (this.slugs.length >= COMPARE_MAX) {
                    this.full = true;
                    setTimeout(() => (this.full = false), 2500);
                    return;
                }
                this.slugs = [...this.slugs, slug];
            }
            this.persist();
        },

        clear() {
            this.slugs = [];
            this.persist();
        },

        /** Tira da lista os imóveis que já não existem no site. */
        prune(valid) {
            const keep = this.slugs.filter((s) => valid.includes(s));
            if (keep.length !== this.slugs.length) {
                this.slugs = keep;
                this.persist();
            }
        },

        get count() {
            return this.slugs.length;
        },

        persist() {
            try {
                window.localStorage.setItem(COMPARE_KEY, JSON.stringify(this.slugs));
            } catch {
                /* armazenamento indisponível — a escolha vive só nesta sessão */
            }
        },
    });
});

/*
 * Imagens do CRM: se um URL falhar, troca pelo placeholder local em vez de
 * mostrar o ícone de imagem partida.
 */
document.addEventListener(
    'error',
    (event) => {
        const el = event.target;
        if (el instanceof HTMLImageElement && el.dataset.fallback && el.src !== el.dataset.fallback) {
            el.src = el.dataset.fallback;
            el.removeAttribute('srcset');
        }
    },
    true,
);
