<template>
  <div
    ref="root"
    class="recon"
    :class="{ 'is-visible': visible }"
  >
    <figure class="recon__figure">
      <svg
        class="recon__svg"
        viewBox="0 0 640 320"
        role="img"
        aria-labelledby="recon-svg-title"
      >
        <title id="recon-svg-title">
          Circuito de ejemplo: la alimentación entra a una planta concentradora y sale como concentrado y relave.
          El grosor de cada corriente es proporcional a sus toneladas conciliadas.
        </title>
        <g
          v-for="s in streamList"
          :key="s.id"
          class="recon__stream"
          :class="{ 'is-active': selected === s.id }"
          @click="selected = s.id"
        >
          <path
            class="recon__pipe"
            :d="s.path"
            pathLength="1"
            :stroke-width="widthFor(s.id)"
          />
          <path
            class="recon__flow"
            :d="s.path"
            :style="{ animationDuration: flowDuration(s.id) }"
          />
          <text
            class="recon__label"
            :x="s.labelX"
            :y="s.labelY"
            :text-anchor="s.anchor"
          >{{ s.name }}</text>
          <text
            class="recon__value"
            :x="s.labelX"
            :y="s.labelY + 20"
            :text-anchor="s.anchor"
          >{{ fmt(result.streams[s.id].mass, 0) }} t a {{ fmt(result.streams[s.id].grade, 1) }}% Fe</text>
        </g>
        <rect
          class="recon__node"
          x="262"
          y="118"
          width="116"
          height="84"
          rx="6"
        />
        <text
          class="recon__node-label"
          x="320"
          y="156"
          text-anchor="middle"
        >Planta</text>
        <text
          class="recon__node-label"
          x="320"
          y="174"
          text-anchor="middle"
        >concentradora</text>
      </svg>
      <figcaption class="recon__note">
        Simulación ilustrativa con valores de ejemplo. Se calcula en tu navegador con un método simplificado y no usa el motor de cálculo de Sondek.
      </figcaption>
    </figure>

    <div class="recon__panel">
      <div
        class="recon__tabs"
        role="group"
        aria-label="Corriente a editar"
      >
        <button
          v-for="s in streamList"
          :key="s.id"
          type="button"
          class="recon__tab"
          :aria-pressed="String(selected === s.id)"
          @click="selected = s.id"
        >
          {{ s.name }}
        </button>
      </div>

      <div class="recon__field">
        <label
          class="recon__field-label"
          for="recon-mass"
        >
          Toneladas medidas
          <output for="recon-mass">{{ fmt(measured[selected].mass, 0) }} t</output>
        </label>
        <input
          id="recon-mass"
          v-model.number="measured[selected].mass"
          class="recon__range"
          type="range"
          :min="ranges[selected].mass[0]"
          :max="ranges[selected].mass[1]"
          step="10"
        >
      </div>

      <div class="recon__field">
        <label
          class="recon__field-label"
          for="recon-grade"
        >
          Ley de Fe medida
          <output for="recon-grade">{{ fmt(measured[selected].grade, 1) }}%</output>
        </label>
        <input
          id="recon-grade"
          v-model.number="measured[selected].grade"
          class="recon__range"
          type="range"
          :min="ranges[selected].grade[0]"
          :max="ranges[selected].grade[1]"
          step="0.1"
        >
      </div>

      <fieldset class="recon__field">
        <legend class="recon__field-label">
          Tolerancia de la medición
        </legend>
        <div class="recon__chips">
          <label
            v-for="t in tolerances"
            :key="t"
            class="recon__chip"
          >
            <input
              v-model.number="tolerance[selected]"
              type="radio"
              name="recon-tolerance"
              :value="t"
            >
            <span>±{{ t }}%</span>
          </label>
        </div>
        <p class="recon__hint">
          Una tolerancia mayor deja que esta corriente absorba más diferencia al conciliar.
        </p>
      </fieldset>

      <button
        type="button"
        class="recon__reset"
        @click="reset"
      >
        Restablecer valores de ejemplo
      </button>
    </div>

    <div class="recon__results">
      <table class="recon__table">
        <caption class="sr-only">
          Valores medidos y conciliados por corriente
        </caption>
        <thead>
          <tr>
            <th scope="col">
              Corriente
            </th>
            <th scope="col">
              Toneladas medidas
            </th>
            <th scope="col">
              Conciliadas
            </th>
            <th scope="col">
              Ley Fe medida
            </th>
            <th scope="col">
              Conciliada
            </th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="s in streamList"
            :key="s.id"
            :class="{ 'is-active': selected === s.id }"
          >
            <th scope="row">
              {{ s.name }}
            </th>
            <td>{{ fmt(measured[s.id].mass, 0) }}</td>
            <td>
              {{ fmt(result.streams[s.id].mass, 0) }}
              <span class="recon__delta">{{ delta(result.streams[s.id].mass, measured[s.id].mass) }}</span>
            </td>
            <td>{{ fmt(measured[s.id].grade, 2) }}</td>
            <td>
              {{ fmt(result.streams[s.id].grade, 2) }}
              <span class="recon__delta">{{ delta(result.streams[s.id].grade, measured[s.id].grade) }}</span>
            </td>
          </tr>
        </tbody>
      </table>

      <dl class="recon__summary">
        <div>
          <dt>Descuadre de masa medido</dt>
          <dd>{{ fmt(result.closure.mass, 2) }}%<small>conciliado: 0,00%</small></dd>
        </div>
        <div>
          <dt>Descuadre de finos medido</dt>
          <dd>{{ fmt(result.closure.fines, 2) }}%<small>conciliado: 0,00%</small></dd>
        </div>
        <div>
          <dt>Recuperación de Fe</dt>
          <dd>{{ fmt(result.recovery, 1) }}%</dd>
        </div>
        <div>
          <dt>Rendimiento másico</dt>
          <dd>{{ fmt(result.yield, 1) }}%</dd>
        </div>
      </dl>
    </div>
  </div>
</template>

<script>
import reconcile from './reconcile'

const sample = () => ({
  feed: { mass: 10000, grade: 45 },
  conc: { mass: 5600, grade: 66.5 },
  tails: { mass: 4250, grade: 18 },
})
const sampleTolerance = () => ({ feed: 3, conc: 2, tails: 6 })

export default {
  data() {
    return {
      selected: 'feed',
      measured: sample(),
      tolerance: sampleTolerance(),
      tolerances: [1, 2, 3, 6, 10],
      visible: false,
      ranges: {
        feed: { mass: [8000, 12000], grade: [35, 55] },
        conc: { mass: [4000, 7000], grade: [60, 70] },
        tails: { mass: [3000, 6000], grade: [10, 28] },
      },
      streamList: [
        {
          id: 'feed', name: 'Alimentación', path: 'M 16 160 L 262 160', labelX: 20, labelY: 104, anchor: 'start',
        },
        {
          id: 'conc', name: 'Concentrado', path: 'M 378 146 C 470 146 500 64 624 64', labelX: 624, labelY: 30, anchor: 'end',
        },
        {
          id: 'tails', name: 'Relave', path: 'M 378 174 C 470 174 500 256 624 256', labelX: 624, labelY: 290, anchor: 'end',
        },
      ],
    }
  },
  computed: {
    result() {
      return reconcile(this.measured, this.tolerance)
    },
  },
  mounted() {
    // Draw the circuit once it scrolls into view.
    if (!('IntersectionObserver' in window)) {
      this.visible = true
      return
    }
    this.observer = new IntersectionObserver(([entry]) => {
      if (!entry.isIntersecting) return
      this.visible = true
      this.observer.disconnect()
    }, { threshold: 0.35 })
    this.observer.observe(this.$refs.root)
  },
  beforeDestroy() {
    if (this.observer) this.observer.disconnect()
  },
  methods: {
    fmt(n, digits) {
      return n.toLocaleString('es-CL', { minimumFractionDigits: digits, maximumFractionDigits: digits })
    },
    delta(reconciled, measured) {
      const d = ((reconciled - measured) / measured) * 100
      if (Math.abs(d) < 0.005) return '±0,00%'
      return `${d > 0 ? '+' : '−'}${this.fmt(Math.abs(d), 2)}%`
    },
    widthFor(id) {
      return 4 + (22 * this.result.streams[id].mass) / this.result.streams.feed.mass
    },
    // Heavier streams flow faster, so the motion carries the tonnage.
    flowDuration(id) {
      return `${(2.4 * this.result.streams.feed.mass) / this.result.streams[id].mass}s`
    },
    reset() {
      this.measured = sample()
      this.tolerance = sampleTolerance()
    },
  },
}
</script>
