<template>
  <div class="dashboard-container" :class="{ 'dark-mode': isDark }">
    <!-- Header Section -->
    <div class="header-section">
      <div class="header-content">
        <div class="logo-section">
          <div class="logo-icon">
            <span class="logo-symbol">⚖️</span>
          </div>
          <div class="title-section">
            <h1 class="main-title">Sistema Balances CMP</h1>
            <p class="subtitle">Gestión Integral de Balances Mineros</p>
          </div>
        </div>
        <div class="header-actions">
          <div class="status-indicator">
            <span class="status-dot"></span>
            <span class="status-text">Sistema Activo</span>
          </div>
        </div>
      </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
      <div class="hero-section">
        <div class="hero-content">
          <div class="hero-text">
            <h2 class="hero-title">Bienvenido al Sistema de Balances</h2>
            <p class="hero-description">
              Plataforma integral para la gestión y análisis de balances mineros de CMP.
              Acceda a reportes detallados, estadísticas en tiempo real y análisis avanzados.
            </p>
            <div class="hero-stats">
              <div class="stat-item">
                <div class="stat-number">24/7</div>
                <div class="stat-label">Disponibilidad</div>
              </div>
              <div class="stat-item">
                <div class="stat-number">99.9%</div>
                <div class="stat-label">Precisión</div>
              </div>
              <div class="stat-item">
                <div class="stat-number">Real-time</div>
                <div class="stat-label">Datos</div>
              </div>
            </div>
          </div>
          <div class="hero-image">
            <b-img
              :src="fondocmp"
              alt="CMP Mining Operations"
              class="main-image"
            />
          </div>
        </div>
      </div>

      <!-- Feature Cards -->
      <div class="features-section">
        <h3 class="section-title">Características Principales</h3>
        <div class="features-grid">
          <div class="feature-card">
            <div class="feature-icon">
              <i class="fas fa-chart-line"></i>
            </div>
            <h4>Análisis Avanzado</h4>
            <p>Reportes detallados y análisis estadísticos en tiempo real</p>
          </div>
          <div class="feature-card">
            <div class="feature-icon">
              <i class="fas fa-database"></i>
            </div>
            <h4>Gestión de Datos</h4>
            <p>Almacenamiento seguro y gestión eficiente de información</p>
          </div>
          <div class="feature-card">
            <div class="feature-icon">
              <i class="fas fa-shield-alt"></i>
            </div>
            <h4>Seguridad Total</h4>
            <p>Protección de datos con estándares de seguridad industrial</p>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { BImg } from 'bootstrap-vue'
import useAppConfig from '@core/app-config/useAppConfig'
import { computed } from '@vue/composition-api'

export default {
  name: 'Dashboard',
  components: {
    BImg,
  },
  setup() {
    const { skin } = useAppConfig()

    const isDark = computed(() => skin.value === 'dark')

    return {
      isDark,
      fondocmp: require('@/assets/images/pages/cmp/cmp2.jpg'),
    }
  },
}
</script>

<style lang="scss" scoped>
.dashboard-container {
  min-height: 100vh;
  background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 50%, #cbd5e1 100%);
  color: #1e293b;
  transition: all 0.3s ease;

  &.dark-mode {
    background: linear-gradient(135deg, #0f172a 0%, #1e293b 50%, #334155 100%);
    color: #f1f5f9;
  }
}

.header-section {
  background: rgba(255, 255, 255, 0.8);
  backdrop-filter: blur(10px);
  border-bottom: 1px solid rgba(0, 0, 0, 0.1);
  padding: 1rem 2rem;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
  transition: all 0.3s ease;

  .dark-mode & {
    background: rgba(15, 23, 42, 0.8);
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.3);
  }
}

.header-content {
  display: flex;
  justify-content: space-between;
  align-items: center;
  max-width: 1200px;
  margin: 0 auto;
}

.logo-section {
  display: flex;
  align-items: center;
  gap: 1rem;
}

.logo-icon {
  width: 50px;
  height: 50px;
  background: linear-gradient(135deg, #3b82f6, #1d4ed8);
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.5rem;
  color: white;
  box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
}

.logo-symbol {
  font-size: 1.8rem;
  line-height: 1;
}

.title-section {
  .main-title {
    font-size: 1.8rem;
    font-weight: 700;
    margin: 0;
    color: #1e293b;
    transition: color 0.3s ease;

    .dark-mode & {
      color: #f1f5f9;
    }
  }

  .subtitle {
    margin: 0.25rem 0 0 0;
    font-size: 0.9rem;
    color: #64748b;
    transition: color 0.3s ease;

    .dark-mode & {
      color: #94a3b8;
    }
  }
}

.status-indicator {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.5rem 1rem;
  background: rgba(34, 197, 94, 0.1);
  border-radius: 20px;
  border: 1px solid rgba(34, 197, 94, 0.2);
  transition: all 0.3s ease;

  .dark-mode & {
    background: rgba(34, 197, 94, 0.2);
    border: 1px solid rgba(34, 197, 94, 0.3);
  }
}

.status-dot {
  width: 8px;
  height: 8px;
  background: #22c55e;
  border-radius: 50%;
  animation: pulse 2s infinite;
}

.status-text {
  color: #166534;
  font-weight: 500;
  transition: color 0.3s ease;

  .dark-mode & {
    color: #22c55e;
  }
}

@keyframes pulse {
  0% { opacity: 1; }
  50% { opacity: 0.5; }
  100% { opacity: 1; }
}

.main-content {
  max-width: 1200px;
  margin: 0 auto;
  padding: 2rem;
}

.hero-section {
  margin-bottom: 4rem;
}

.hero-content {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 3rem;
  align-items: center;
}

.hero-title {
  font-size: 2.5rem;
  font-weight: 700;
  margin-bottom: 1rem;
  color: #1e293b;
  transition: color 0.3s ease;

  .dark-mode & {
    color: #f1f5f9;
  }
}

.hero-description {
  font-size: 1.1rem;
  line-height: 1.6;
  margin-bottom: 2rem;
  color: #475569;
  transition: color 0.3s ease;

  .dark-mode & {
    color: #cbd5e1;
  }
}

.hero-stats {
  display: flex;
  gap: 2rem;
}

.stat-item {
  text-align: center;

  .stat-number {
    font-size: 1.5rem;
    font-weight: 700;
    color: #3b82f6;
    transition: color 0.3s ease;

    .dark-mode & {
      color: #60a5fa;
    }
  }

  .stat-label {
    font-size: 0.9rem;
    color: #64748b;
    font-weight: 500;
    transition: color 0.3s ease;

    .dark-mode & {
      color: #94a3b8;
    }
  }
}

.hero-image {
  .main-image {
    width: 100%;
    height: auto;
    border-radius: 15px;
    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
    transition: transform 0.3s ease;

    &:hover {
      transform: translateY(-5px);
    }
  }
}

.features-section {
  margin-top: 4rem;
}

.section-title {
  text-align: center;
  font-size: 2rem;
  font-weight: 600;
  margin-bottom: 3rem;
  color: #1e293b;
  transition: color 0.3s ease;

  .dark-mode & {
    color: #f1f5f9;
  }
}

.features-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
  gap: 2rem;
}

.feature-card {
  background: rgba(255, 255, 255, 0.9);
  backdrop-filter: blur(10px);
  border: 1px solid rgba(0, 0, 0, 0.1);
  border-radius: 15px;
  padding: 2rem;
  text-align: center;
  transition: all 0.3s ease;
  box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);

  &:hover {
    transform: translateY(-5px);
    background: rgba(255, 255, 255, 1);
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
  }

  .dark-mode & {
    background: rgba(30, 41, 59, 0.9);
    border: 1px solid rgba(255, 255, 255, 0.1);
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.2);

    &:hover {
      background: rgba(30, 41, 59, 1);
      box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
    }
  }

  .feature-icon {
    width: 60px;
    height: 60px;
    background: linear-gradient(135deg, #3b82f6, #1d4ed8);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 1rem;
    font-size: 1.5rem;
    color: white;
    box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
  }

  h4 {
    font-size: 1.3rem;
    font-weight: 600;
    margin-bottom: 1rem;
    color: #1e293b;
    transition: color 0.3s ease;

    .dark-mode & {
      color: #f1f5f9;
    }
  }

  p {
    color: #64748b;
    line-height: 1.5;
    margin: 0;
    transition: color 0.3s ease;

    .dark-mode & {
      color: #cbd5e1;
    }
  }
}

// Responsive Design
@media (max-width: 768px) {
  .hero-content {
    grid-template-columns: 1fr;
    gap: 2rem;
  }

  .hero-title {
    font-size: 2rem;
  }

  .hero-stats {
    justify-content: center;
  }

  .header-content {
    flex-direction: column;
    gap: 1rem;
  }

  .features-grid {
    grid-template-columns: 1fr;
  }
}

@media (max-width: 480px) {
  .main-content {
    padding: 1rem;
  }

  .hero-title {
    font-size: 1.5rem;
  }

  .hero-stats {
    flex-direction: column;
    gap: 1rem;
  }
}
</style>
