<template>
  <div
    ref="root"
    class="home"
  >
    <div
      class="home-loader"
      aria-hidden="true"
    >
      <img
        class="home-loader__logo"
        src="/brand/cmp-logo-white.svg"
        alt=""
      >
    </div>

    <header
      class="home-nav"
      :class="{ 'is-solid': navSolid }"
    >
      <div class="home-nav__inner">
        <RouterLink
          to="/"
          class="home-nav__brand"
        >
          <img
            class="home-nav__logo"
            src="/brand/cmp-logo-white.svg"
            alt="CMP, Compañía Minera del Pacífico"
          >
          <span class="home-nav__product">Balances</span>
        </RouterLink>
        <nav
          class="home-nav__links"
          aria-label="Secciones"
        >
          <a href="#problema">El problema</a>
          <a href="#simulador">Simulador</a>
          <a href="#flujo">Cómo se usa</a>
          <a href="#motor">Motor de cálculo</a>
          <a href="#cmp">CMP</a>
        </nav>
        <RouterLink
          to="/login"
          class="home-btn home-btn--light home-btn--sm"
        >
          Ingresar
        </RouterLink>
      </div>
    </header>

    <main>
      <section
        class="home-hero"
        aria-labelledby="hero-title"
      >
        <div class="home-hero__frame">
          <div
            class="home-hero__media"
            aria-hidden="true"
          >
            <img
              v-if="reducedMotion"
              :src="media('hero', 'webp')"
              alt=""
              class="home-hero__video"
            >
            <video
              v-else
              class="home-hero__video"
              :poster="media('hero', 'webp')"
              autoplay
              muted
              loop
              playsinline
              preload="auto"
            >
              <source
                media="(max-width: 767px)"
                :src="media('hero-720', 'mp4')"
                type="video/mp4"
              >
              <source
                :src="media('hero', 'mp4')"
                type="video/mp4"
              >
            </video>
          </div>

          <div class="home-hero__content">
            <h1
              id="hero-title"
              class="home-hero__title"
            >
              Balances metalúrgicos que cierran.
            </h1>
            <p class="home-hero__lede">
              Conciliamos las toneladas y leyes medidas en cada corriente de las plantas de CMP, para que masa y finos cuadren en cada nodo dentro de las tolerancias que defines.
            </p>
            <div class="home-hero__actions">
              <RouterLink
                to="/login"
                class="home-btn home-btn--ore"
              >
                Ingresar
              </RouterLink>
              <a
                href="#simulador"
                class="home-btn home-btn--ghost"
              >Probar una conciliación</a>
            </div>
          </div>

          <svg
            class="home-hero__flow"
            viewBox="0 0 1200 120"
            preserveAspectRatio="none"
            aria-hidden="true"
          >
            <path
              class="home-hero__flow-line"
              d="M0 70 H560 C640 70 660 30 760 30 H1200"
            />
            <path
              class="home-hero__flow-line"
              d="M560 70 C640 70 660 100 760 100 H1200"
            />
            <path
              class="home-hero__flow-dash"
              d="M0 70 H560 C640 70 660 30 760 30 H1200"
            />
            <path
              class="home-hero__flow-dash home-hero__flow-dash--slow"
              d="M560 70 C640 70 660 100 760 100 H1200"
            />
            <circle
              class="home-hero__flow-node"
              cx="560"
              cy="70"
              r="5"
            />
          </svg>

          <a
            href="#problema"
            class="home-hero__cue"
          >
            Desliza para explorar
            <svg
              viewBox="0 0 16 16"
              width="14"
              height="14"
              aria-hidden="true"
            ><path
              d="M8 2v11M3 8.5 8 13.5l5-5"
              fill="none"
              stroke="currentColor"
              stroke-width="1.5"
            /></svg>
          </a>
        </div>
      </section>

      <div
        class="home-strip"
        aria-label="Lo que maneja el sistema"
      >
        <ul
          v-for="copy in 2"
          :key="copy"
          class="home-strip__track"
          :aria-hidden="copy === 2 ? 'true' : null"
        >
          <li
            v-for="term in stripTerms"
            :key="term"
          >
            {{ term }}
          </li>
        </ul>
      </div>

      <section
        id="problema"
        class="home-split"
        aria-labelledby="problem-title"
      >
        <div class="home-split__media">
          <video
            class="home-split__video"
            :poster="media('problem', 'webp')"
            :data-src="media('problem', 'mp4')"
            muted
            loop
            playsinline
            preload="none"
            aria-hidden="true"
          />
        </div>
        <div class="home-split__card">
          <h2
            id="problem-title"
            class="home-display"
            data-reveal
          >
            Las mediciones nunca cierran solas.
          </h2>
          <div data-reveal>
            <p>
              Pesómetros, muestreos y análisis químicos tienen error. Al sumar las corrientes de un proceso, lo que entra casi nunca coincide con lo que sale, ni en toneladas ni en metal fino.
            </p>
            <p>
              El sistema busca el conjunto de valores más cercano a lo medido que respeta la conservación de masa y de finos, y te muestra cuánto se ajustó cada medición.
            </p>
          </div>
        </div>
      </section>

      <section
        id="simulador"
        class="home-sim"
        aria-labelledby="sim-title"
      >
        <div class="home-wrap">
          <div
            class="home-sim__head"
            data-reveal
          >
            <h2
              id="sim-title"
              class="home-display"
            >
              Prueba una conciliación.
            </h2>
            <p>
              Mueve una medición o su tolerancia y observa cómo se reparte la diferencia entre las corrientes hasta que el circuito cierra.
            </p>
          </div>
          <ReconciliationDemo />
        </div>
      </section>

      <section
        id="flujo"
        class="home-steps"
        aria-labelledby="flow-title"
      >
        <div class="home-wrap">
          <h2
            id="flow-title"
            class="home-display home-steps__title"
            data-reveal
          >
            De la planilla al balance cerrado.
          </h2>
          <div class="home-steps__grid">
            <ol class="home-steps__list">
              <li
                v-for="(step, i) in steps"
                :key="step.title"
                class="home-steps__item"
                :class="{ 'is-active': activeStep === i }"
                data-step-trigger
              >
                <span
                  class="home-steps__num"
                  aria-hidden="true"
                >{{ i + 1 }}</span>
                <h3>{{ step.title }}</h3>
                <p>{{ step.text }}</p>
                <img
                  class="home-steps__poster"
                  :src="media(step.media, 'webp')"
                  alt=""
                  loading="lazy"
                  width="1920"
                  height="1080"
                >
              </li>
            </ol>
            <div
              class="home-steps__media"
              aria-hidden="true"
            >
              <video
                v-for="(step, i) in steps"
                :key="step.media"
                class="home-steps__video"
                :class="{ 'is-active': activeStep === i }"
                :poster="media(step.media, 'webp')"
                :data-src="media(step.media, 'mp4')"
                :data-step="i"
                muted
                loop
                playsinline
                preload="none"
              />
            </div>
          </div>
        </div>
      </section>

      <section
        id="motor"
        class="home-engine"
        aria-labelledby="engine-title"
      >
        <div class="home-wrap home-engine__grid">
          <div data-reveal>
            <h2
              id="engine-title"
              class="home-display"
            >
              Un problema de optimización, resuelto en serio.
            </h2>
            <p class="home-engine__lede">
              Cada balance se plantea como un problema de mínimos cuadrados con restricciones y lo resuelve un optimizador no lineal: el resultado es el ajuste más pequeño que hace cerrar el circuito.
            </p>
            <div
              class="home-engine__math"
              role="math"
              aria-label="Minimizar la suma de los cuadrados de las diferencias entre toneladas y leyes medidas y conciliadas, sujeto a que la matriz del circuito por las toneladas sea cero y por los finos de cada componente sea cero, con cada valor dentro de su tolerancia."
            >
              <p><span class="home-engine__op">min</span> Σ<sub>i</sub> (m<sub>i</sub> − m̂<sub>i</sub>)² + Σ<sub>i,k</sub> (g<sub>ik</sub> − ĝ<sub>ik</sub>)²</p>
              <p><span class="home-engine__op">s.a.</span> A · m̂ = 0</p>
              <p><span class="home-engine__op" /> A · (m̂ ∘ ĝ<sub>k</sub>) = 0 para cada componente k</p>
              <p><span class="home-engine__op" /> m̂, ĝ dentro de la tolerancia de cada medición</p>
            </div>
          </div>
          <dl
            class="home-engine__specs"
            data-reveal
          >
            <div
              v-for="spec in specs"
              :key="spec.term"
            >
              <dt>{{ spec.term }}</dt>
              <dd>{{ spec.text }}</dd>
            </div>
          </dl>
        </div>
      </section>

      <section
        id="cmp"
        class="home-cmp"
        aria-labelledby="cmp-title"
      >
        <div class="home-wrap">
          <div
            class="home-cmp__head"
            data-reveal
          >
            <p class="home-cmp__eyebrow">
              Compañía Minera del Pacífico
            </p>
            <h2
              id="cmp-title"
              class="home-display"
            >
              Creamos una minería diferente, para el desarrollo sostenible del territorio y su gente.
            </h2>
            <p>
              Cerca de 100 años de tradición minera y el principal exportador de hierro de Chile. Una empresa del grupo CAP, con operaciones en tres valles de las regiones de Atacama y Coquimbo.
            </p>
          </div>
          <ul class="home-cmp__valleys">
            <li
              v-for="valle in valles"
              :key="valle.name"
              class="home-cmp__valley"
              :style="{ '--valle': valle.color }"
              data-reveal
            >
              <h3>{{ valle.name }}</h3>
              <ul>
                <li
                  v-for="op in valle.ops"
                  :key="op"
                >
                  {{ op }}
                </li>
              </ul>
            </li>
          </ul>
        </div>
      </section>

      <section
        class="home-cta"
        aria-labelledby="cta-title"
      >
        <div class="home-cta__frame">
          <video
            class="home-cta__video"
            :poster="media('cta', 'webp')"
            :data-src="media('cta', 'mp4')"
            muted
            loop
            playsinline
            preload="none"
            aria-hidden="true"
          />
          <div
            class="home-cta__content"
            data-reveal
          >
            <h2
              id="cta-title"
              class="home-display"
            >
              Cierra el período sin cuadrar a mano.
            </h2>
            <div class="home-cta__actions">
              <RouterLink
                to="/login"
                class="home-btn home-btn--ore"
              >
                Ingresar
              </RouterLink>
              <RouterLink
                to="/forgot-password"
                class="home-btn home-btn--ghost"
              >
                Recuperar contraseña
              </RouterLink>
            </div>
          </div>
        </div>
      </section>
    </main>

    <footer class="home-footer">
      <div class="home-wrap home-footer__inner">
        <p>
          CMP, Compañía Minera del Pacífico · Una empresa del grupo CAP ·
          <a
            href="https://www.cmp.cl"
            rel="noopener"
            target="_blank"
          >cmp.cl</a>
        </p>
        <p>
          Videos de
          <a
            href="https://www.pexels.com/license/"
            rel="noopener"
            target="_blank"
          >Pexels</a>, con fines ilustrativos.
        </p>
      </div>
    </footer>
  </div>
</template>

<script setup>
import ReconciliationDemo from '@/views/home/ReconciliationDemo.vue'
import initHomeMotion from '@/views/home/homeMotion'

// Public landing; logged-in users are sent to their home route by the router guard (redirectIfLoggedIn).
definePage({
  meta: {
    layout: 'blank',
    public: true,
    redirectIfLoggedIn: true,
  },
})

const root = ref(null)
const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches
const navSolid = ref(false)
const activeStep = ref(0)

const stripTerms = [
  'Toneladas secas por corriente',
  'Leyes de Fe y otros componentes',
  'Balance de finos por nodo',
  'Tolerancia por medición',
  'Carga desde Excel',
  'Resultados exportables',
  'Historial por valle y proceso',
]

const steps = [
  { title: 'Elige valle y proceso', text: 'Los balances se ordenan por empresa, valle y proceso, cada uno con sus corrientes configuradas.', media: 'step1' },
  { title: 'Carga la planilla', text: 'Sube el Excel del período con toneladas, leyes y restricciones de cada corriente.', media: 'step2' },
  { title: 'Revisa las tolerancias', text: 'Ajusta cuánto puede moverse cada medición antes de conciliar.', media: 'step3' },
  { title: 'Concilia y guarda', text: 'Compara lo medido con lo conciliado y guarda el balance con un nombre reconocible.', media: 'step4' },
  { title: 'Exporta y sigue la actividad', text: 'Descarga los resultados en Excel y revisa los balances corridos por valle y proceso en el dashboard.', media: 'step5' },
]

const specs = [
  { term: 'Varios componentes', text: 'Concilia toneladas y todas las leyes de cada corriente en un mismo cálculo.' },
  { term: 'Restricciones por nodo', text: 'Masa y finos se conservan en cada etapa del circuito, no solo en el total.' },
  { term: 'Tolerancias por medición', text: 'Cada tonelaje y cada ley tiene su rango permitido, y ningún valor conciliado sale de él.' },
  { term: 'Acceso por rol', text: 'Administradores, operadores y visualizadores ven y hacen solo lo que les corresponde.' },
]

// Operations and valley colors as listed on cmp.cl (2026-11).
const valles = [
  { name: 'Valle de Copiapó', color: 'var(--valle-copiapo)', ops: ['Mina Cerro Negro Norte', 'Planta Magnetita', 'Puerto Punta Totoralillo'] },
  { name: 'Valle del Huasco', color: 'var(--valle-huasco)', ops: ['Mina Los Colorados', 'Planta de Pellets', 'Puerto Guacolda II', 'Depósito de Relaves Filtrados'] },
  { name: 'Valle del Elqui', color: 'var(--valle-elqui)', ops: ['Minas El Romeral', 'Mina de Pleito', 'Puerto Guayacán'] },
]

// Videos and posters live in Laravel's public/media/home (outside the Vite build).
const media = (name, ext) => `/media/home/${name}.${ext}`

let destroyMotion = null
onMounted(() => {
  destroyMotion = initHomeMotion(root.value, {
    reducedMotion,
    onNavSolid: solid => { navSolid.value = solid },
    onStep: index => { activeStep.value = index },
  })
})
onBeforeUnmount(() => destroyMotion?.())
</script>

<style lang="scss">
@import '@/views/home/home.scss';
</style>
