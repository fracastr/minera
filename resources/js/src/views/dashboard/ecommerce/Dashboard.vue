<template>
  <div class="dashboard-cmp-container" :class="{ 'dark-mode': isDark }">
    <!-- Header Section -->
    <div class="dashboard-cmp-header-section">
      <div class="dashboard-cmp-header-content">
        <div class="dashboard-cmp-logo-section">
          <div class="dashboard-cmp-logo-icon">
            <span class="dashboard-cmp-logo-symbol">⚖️</span>
          </div>
          <div class="dashboard-cmp-title-section">
            <h1 class="dashboard-cmp-main-title">{{ $t('dashboard.title') }}</h1>
            <p class="dashboard-cmp-subtitle">{{ $t('dashboard.subtitle') }}</p>
          </div>
        </div>
        <div class="dashboard-cmp-header-actions">
          <div class="dashboard-cmp-status-indicator">
            <span class="dashboard-cmp-status-dot"></span>
            <span class="dashboard-cmp-status-text">{{ $t('dashboard.status') }}</span>
          </div>
        </div>
      </div>
    </div>

    <!-- Main Content -->
    <div class="dashboard-cmp-main-content">
      <div class="dashboard-cmp-hero-section">
        <div class="dashboard-cmp-hero-content">
          <div class="dashboard-cmp-hero-text">
            <h2 class="dashboard-cmp-hero-title">{{ $t('dashboard.welcome') }}</h2>
            <p class="dashboard-cmp-hero-description">
              {{ $t('dashboard.description') }}
            </p>
            <div class="dashboard-cmp-hero-stats">
              <div class="dashboard-cmp-stat-item">
                <div class="dashboard-cmp-stat-number">24/7</div>
                <div class="dashboard-cmp-stat-label">{{ $t('dashboard.stats.availability') }}</div>
              </div>
              <div class="dashboard-cmp-stat-item">
                <div class="dashboard-cmp-stat-number">99.9%</div>
                <div class="dashboard-cmp-stat-label">{{ $t('dashboard.stats.accuracy') }}</div>
              </div>
              <div class="dashboard-cmp-stat-item">
                <div class="dashboard-cmp-stat-number">{{ $t('dashboard.stats.realtime') }}</div>
                <div class="dashboard-cmp-stat-label">{{ $t('dashboard.stats.data') }}</div>
              </div>
            </div>
          </div>
          <div class="dashboard-cmp-hero-image">
            <b-img
              :src="fondocmp"
              alt="CMP Mining Operations"
              class="dashboard-cmp-main-image"
            />
          </div>
        </div>
      </div>

      <!-- Feature Cards -->
      <div class="dashboard-cmp-features-section">
        <h3 class="dashboard-cmp-section-title">{{ $t('dashboard.features.title') }}</h3>
        <div class="dashboard-cmp-features-grid">
          <div class="dashboard-cmp-feature-card">
            <div class="dashboard-cmp-feature-icon">
              <feather-icon icon="TrendingUpIcon" size="24" />
            </div>
            <h4>{{ $t('dashboard.features.advancedAnalysis.title') }}</h4>
            <p>{{ $t('dashboard.features.advancedAnalysis.description') }}</p>
          </div>
          <div class="dashboard-cmp-feature-card">
            <div class="dashboard-cmp-feature-icon">
              <feather-icon icon="HardDriveIcon" size="24" />
            </div>
            <h4>{{ $t('dashboard.features.dataManagement.title') }}</h4>
            <p>{{ $t('dashboard.features.dataManagement.description') }}</p>
          </div>
          <div class="dashboard-cmp-feature-card">
            <div class="dashboard-cmp-feature-icon">
              <feather-icon icon="ShieldIcon" size="24" />
            </div>
            <h4>{{ $t('dashboard.features.security.title') }}</h4>
            <p>{{ $t('dashboard.features.security.description') }}</p>
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
.dashboard-cmp-container {
  min-height: 100vh !important;
  background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 50%, #cbd5e1 100%) !important;
  color: #1e293b !important;
  transition: all 0.3s ease !important;

  &.dark-mode {
    background: linear-gradient(135deg, #0f172a 0%, #1e293b 50%, #334155 100%) !important;
    color: #f1f5f9 !important;
  }
}

.dashboard-cmp-header-section {
  background: rgba(255, 255, 255, 0.8) !important;
  backdrop-filter: blur(10px) !important;
  border-bottom: 1px solid rgba(0, 0, 0, 0.1) !important;
  padding: 1rem 2rem !important;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1) !important;
  transition: all 0.3s ease !important;

  .dark-mode & {
    background: rgba(15, 23, 42, 0.8) !important;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1) !important;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.3) !important;
  }
}

.dashboard-cmp-header-content {
  display: flex !important;
  justify-content: space-between !important;
  align-items: center !important;
  max-width: 1200px !important;
  margin: 0 auto !important;
}

.dashboard-cmp-logo-section {
  display: flex !important;
  align-items: center !important;
  gap: 1rem !important;
}

.dashboard-cmp-logo-icon {
  width: 50px !important;
  height: 50px !important;
  background: linear-gradient(135deg, #3b82f6, #1d4ed8) !important;
  border-radius: 50% !important;
  display: flex !important;
  align-items: center !important;
  justify-content: center !important;
  font-size: 1.5rem !important;
  color: white !important;
  box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3) !important;
}

.dashboard-cmp-logo-symbol {
  font-size: 1.8rem !important;
  line-height: 1 !important;
}

.dashboard-cmp-title-section {
  .dashboard-cmp-main-title {
    font-size: 2.2rem !important;
    font-weight: 800 !important;
    margin: 0 !important;
    color: #1e293b !important;
    transition: color 0.3s ease !important;
    letter-spacing: -0.025em !important;
    line-height: 1.1 !important;
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif !important;

    .dark-mode & {
      color: #f1f5f9 !important;
    }
  }

  .dashboard-cmp-subtitle {
    margin: 0.5rem 0 0 0 !important;
    font-size: 1rem !important;
    color: #64748b !important;
    transition: color 0.3s ease !important;
    font-weight: 500 !important;
    letter-spacing: 0.01em !important;
    line-height: 1.4 !important;
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif !important;

    .dark-mode & {
      color: #94a3b8 !important;
    }
  }
}

.dashboard-cmp-status-indicator {
  display: flex !important;
  align-items: center !important;
  gap: 0.5rem !important;
  padding: 0.75rem 1.5rem !important;
  background: rgba(34, 197, 94, 0.15) !important;
  border-radius: 20px !important;
  border: 1px solid rgba(34, 197, 94, 0.3) !important;
  transition: all 0.3s ease !important;
  box-shadow: 0 2px 8px rgba(34, 197, 94, 0.2) !important;

  .dark-mode & {
    background: rgba(34, 197, 94, 0.25) !important;
    border: 1px solid rgba(34, 197, 94, 0.4) !important;
    box-shadow: 0 2px 8px rgba(34, 197, 94, 0.3) !important;
  }
}

.dashboard-cmp-status-dot {
  width: 8px !important;
  height: 8px !important;
  background: #22c55e !important;
  border-radius: 50% !important;
  animation: pulse 2s infinite !important;
}

.dashboard-cmp-status-text {
  color: #166534 !important;
  font-weight: 600 !important;
  transition: color 0.3s ease !important;
  font-size: 0.875rem !important;
  letter-spacing: 0.02em !important;
  text-transform: uppercase !important;
  font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif !important;

  .dark-mode & {
    color: #22c55e !important;
  }
}

@keyframes pulse {
  0% { opacity: 1 !important; }
  50% { opacity: 0.5 !important; }
  100% { opacity: 1 !important; }
}

.dashboard-cmp-main-content {
  max-width: 1200px !important;
  margin: 0 auto !important;
  padding: 2rem !important;
}

.dashboard-cmp-hero-section {
  margin-bottom: 4rem !important;
  background: rgba(255, 255, 255, 0.7) !important;
  border-radius: 20px !important;
  padding: 3rem !important;
  backdrop-filter: blur(10px) !important;
  border: 1px solid rgba(255, 255, 255, 0.5) !important;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08) !important;

  .dark-mode & {
    background: rgba(30, 41, 59, 0.7) !important;
    border: 1px solid rgba(255, 255, 255, 0.1) !important;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.2) !important;
  }
}

.dashboard-cmp-hero-content {
  display: grid !important;
  grid-template-columns: 1fr 1fr !important;
  gap: 4rem !important;
  align-items: center !important;
  min-height: 400px !important;
}

.dashboard-cmp-hero-title {
  font-size: 3.5rem !important;
  font-weight: 800 !important;
  margin-bottom: 1.5rem !important;
  color: #1e293b !important;
  letter-spacing: -0.02em !important;
  transition: color 0.3s ease !important;
  line-height: 1.2 !important;
  font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif !important;
  text-shadow: none !important;

  .dark-mode & {
    color: #f1f5f9 !important;
  }
}

.dashboard-cmp-hero-description {
  font-size: 1.2rem !important;
  line-height: 1.6 !important;
  margin-bottom: 2.5rem !important;
  color: #475569 !important;
  transition: color 0.3s ease !important;
  font-weight: 400 !important;
  letter-spacing: 0.01em !important;
  font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif !important;
  text-shadow: none !important;

  .dark-mode & {
    color: #cbd5e1 !important;
  }
}

.dashboard-cmp-hero-stats {
  display: flex !important;
  gap: 2rem !important;
}

.dashboard-cmp-stat-item {
  text-align: center !important;
  padding: 1.5rem !important;
  background: rgba(255, 255, 255, 0.8) !important;
  border-radius: 12px !important;
  backdrop-filter: blur(8px) !important;
  border: 1px solid rgba(0, 0, 0, 0.08) !important;
  transition: all 0.3s ease !important;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06) !important;

  &:hover {
    transform: translateY(-2px) !important;
    background: rgba(255, 255, 255, 0.95) !important;
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.1) !important;
  }

  .dark-mode & {
    background: transparent !important;
    border: 1px solid rgba(255, 255, 255, 0.1) !important;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2) !important;

    &:hover {
      background: rgba(255, 255, 255, 0.05) !important;
      box-shadow: 0 4px 16px rgba(0, 0, 0, 0.3) !important;
    }
  }

  .dashboard-cmp-stat-number {
    font-size: 1.75rem !important;
    font-weight: 700 !important;
    color: #1e293b !important;
    transition: color 0.3s ease !important;
    letter-spacing: -0.01em !important;
    line-height: 1 !important;
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif !important;
    margin-bottom: 0.5rem !important;

    .dark-mode & {
      color: #f1f5f9 !important;
    }
  }

  .dashboard-cmp-stat-label {
    font-size: 0.875rem !important;
    color: #64748b !important;
    font-weight: 500 !important;
    transition: color 0.3s ease !important;
    letter-spacing: 0.01em !important;
    text-transform: uppercase !important;
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif !important;

    .dark-mode & {
      color: #94a3b8 !important;
    }
  }
}

.dashboard-cmp-hero-image {
  .dashboard-cmp-main-image {
    width: 100% !important;
    height: auto !important;
    border-radius: 15px !important;
    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3) !important;
    transition: transform 0.3s ease !important;

    &:hover {
      transform: translateY(-5px) !important;
    }
  }
}

.dashboard-cmp-features-section {
  margin-top: 4rem !important;
}

.dashboard-cmp-section-title {
  text-align: center !important;
  font-size: 2.5rem !important;
  font-weight: 800 !important;
  margin-bottom: 3rem !important;
  color: #1e293b !important;
  transition: color 0.3s ease !important;
  letter-spacing: -0.025em !important;
  line-height: 1.2 !important;
  font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif !important;

  .dark-mode & {
    color: #f1f5f9 !important;
  }
}

.dashboard-cmp-features-grid {
  display: grid !important;
  grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)) !important;
  gap: 2rem !important;
}

.dashboard-cmp-feature-card {
  background: rgba(255, 255, 255, 0.95) !important;
  backdrop-filter: blur(20px) !important;
  border: 1px solid rgba(255, 255, 255, 0.3) !important;
  border-radius: 20px !important;
  padding: 2.5rem !important;
  text-align: center !important;
  transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1) !important;
  box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05) !important;
  position: relative !important;
  overflow: hidden !important;

  &::before {
    content: '' !important;
    position: absolute !important;
    top: 0 !important;
    left: 0 !important;
    right: 0 !important;
    height: 4px !important;
    background: linear-gradient(135deg, #3b82f6, #1d4ed8) !important;
    transform: scaleX(0) !important;
    transition: transform 0.3s ease !important;
  }

  &:hover {
    transform: translateY(-8px) !important;
    background: rgba(255, 255, 255, 1) !important;
    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.12) !important;

    &::before {
      transform: scaleX(1) !important;
    }
  }

  .dark-mode & {
    background: rgba(30, 41, 59, 0.95) !important;
    border: 1px solid rgba(255, 255, 255, 0.1) !important;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.2) !important;

    &:hover {
      background: rgba(30, 41, 59, 1) !important;
      box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3) !important;
    }
  }

  .dashboard-cmp-feature-icon {
    width: 70px !important;
    height: 70px !important;
    background: linear-gradient(135deg, #3b82f6, #1d4ed8) !important;
    border-radius: 18px !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    margin: 0 auto 1.5rem !important;
    color: white !important;
    box-shadow: 0 8px 20px rgba(59, 130, 246, 0.3) !important;
    transition: all 0.3s ease !important;

    .feather-icon {
      color: white !important;
    }

    svg {
      width: 24px !important;
      height: 24px !important;
      stroke: white !important;
      stroke-width: 2 !important;
      fill: none !important;
    }

    &:hover {
      transform: scale(1.1) !important;
      box-shadow: 0 12px 30px rgba(59, 130, 246, 0.4) !important;
    }
  }

  h4 {
    font-size: 1.5rem !important;
    font-weight: 700 !important;
    margin-bottom: 1rem !important;
    color: #1e293b !important;
    transition: color 0.3s ease !important;
    letter-spacing: -0.01em !important;
    line-height: 1.3 !important;
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif !important;

    .dark-mode & {
      color: #f1f5f9 !important;
    }
  }

  p {
    color: #64748b !important;
    line-height: 1.6 !important;
    margin: 0 !important;
    transition: color 0.3s ease !important;
    font-size: 1rem !important;
    font-weight: 400 !important;
    letter-spacing: 0.01em !important;
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif !important;

    .dark-mode & {
      color: #cbd5e1 !important;
    }
  }
}

// Responsive Design
@media (max-width: 768px) {
  .dashboard-cmp-hero-content {
    grid-template-columns: 1fr !important;
    gap: 2rem !important;
  }

  .dashboard-cmp-hero-title {
    font-size: 2.5rem !important;
    letter-spacing: -0.02em !important;
  }

  .dashboard-cmp-hero-description {
    font-size: 1.125rem !important;
    line-height: 1.6 !important;
  }

  .dashboard-cmp-hero-stats {
    justify-content: center !important;
    gap: 1rem !important;
  }

  .dashboard-cmp-stat-item {
    padding: 1rem !important;
  }

  .dashboard-cmp-stat-number {
    font-size: 1.5rem !important;
  }

  .dashboard-cmp-header-content {
    flex-direction: column !important;
    gap: 1rem !important;
  }

  .dashboard-cmp-features-grid {
    grid-template-columns: 1fr !important;
  }

  .dashboard-cmp-section-title {
    font-size: 2rem !important;
  }

  .dashboard-cmp-feature-card {
    padding: 2rem !important;
  }
}

@media (max-width: 480px) {
  .dashboard-cmp-main-content {
    padding: 1rem !important;
  }

  .dashboard-cmp-hero-title {
    font-size: 2rem !important;
    letter-spacing: -0.01em !important;
  }

  .dashboard-cmp-hero-description {
    font-size: 1rem !important;
    line-height: 1.5 !important;
  }

  .dashboard-cmp-hero-stats {
    flex-direction: column !important;
    gap: 1rem !important;
  }

  .dashboard-cmp-stat-item {
    padding: 1.25rem !important;
  }

  .dashboard-cmp-section-title {
    font-size: 1.75rem !important;
  }

  .dashboard-cmp-feature-card {
    padding: 1.5rem !important;
  }

  .dashboard-cmp-feature-card h4 {
    font-size: 1.25rem !important;
  }
}
</style>
