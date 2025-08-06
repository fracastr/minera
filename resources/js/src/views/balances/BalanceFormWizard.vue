<template>
  <div class="balance-form-container">
    <!-- Header Section -->
    <div class="form-header">
      <div class="header-content">
        <div class="header-icon">
          <feather-icon icon="TrendingUpIcon" size="32" />
        </div>
        <div class="header-text">
          <h2 class="main-title">{{ $t('balanceFormWizard.header.title') }}</h2>
          <p class="subtitle">{{ $t('balanceFormWizard.header.subtitle') }}</p>
        </div>
      </div>
    </div>

    <!-- Loading Overlay -->
    <div v-if="loading" class="loading-overlay">
      <div class="loading-card">
        <div class="loading-animation">
          <div class="loading-spinner">
            <div class="spinner-ring"></div>
            <div class="spinner-ring"></div>
            <div class="spinner-ring"></div>
          </div>
        </div>
        <div class="loading-content">
          <h4 class="loading-title">{{ $t('balanceFormWizard.loading.title') }}</h4>
          <p class="loading-subtitle">{{ $t('balanceFormWizard.loading.subtitle') }}</p>
          <div class="loading-progress">
            <div class="progress-bar">
              <div class="progress-fill"></div>
            </div>
            <span class="progress-text">{{ $t('balanceFormWizard.loading.progress') }}</span>
          </div>
        </div>
        <div class="loading-footer">
          <feather-icon icon="DatabaseIcon" size="16" class="mr-2" />
          <span>{{ $t('balanceFormWizard.loading.systemName') }}</span>
        </div>
      </div>
    </div>

    <form-wizard
      color="#7367F0"
      :title="null"
      :subtitle="null"
      shape="square"
      :finish-button-text="$t('balanceFormWizard.finishButton')"
      :back-button-text="$t('balanceFormWizard.backButton')"
      :next-button-text="$t('balanceFormWizard.nextButton')"
      class="wizard-enhanced"
      @on-complete="formSubmitted"
      :disabled="loading"
    >

      <!-- Valle Selection Tab -->
      <tab-content
        :title="$t('balanceFormWizard.valleSelection.title')"
        :before-change="validationForm"
        class="tab-enhanced"
      >
        <validation-observer
          ref="accountRules"
          tag="form"
        >
          <div class="tab-content-enhanced">
            <div class="section-card">
              <div class="section-header">
                <div class="section-icon">
                  <feather-icon icon="BarChart2Icon" size="28" />
                </div>
                <div class="section-info">
                  <h4 class="section-title">{{ $t('balanceFormWizard.valleSelection.systemTitle') }}</h4>
                  <p class="section-description">{{ $t('balanceFormWizard.valleSelection.subtitle') }}</p>
                </div>
              </div>

              <div class="form-content">
                <validation-provider
                  #default="{ errors }"
                  :name="$t('balanceFormWizard.valleSelection.name')"
                  rules="required"
                >
                  <b-form-group
                    :label="$t('balanceFormWizard.valleSelection.label')"
                    label-for="valle"
                    :state="errors.length > 0 ? false:null"
                    class="form-group-enhanced"
                  >
                    <b-form-select
                      v-model="valle"
                      :options="valles"
                      @change="valles_change"
                      class="form-select-enhanced"
                      :class="{ 'is-invalid': errors.length > 0 }"
                    />
                    <b-form-invalid-feedback :state="errors.length > 0 ? false:null">
                      {{ errors[0] }}
                    </b-form-invalid-feedback>
                  </b-form-group>
                </validation-provider>
              </div>
            </div>
          </div>
        </validation-observer>
      </tab-content>

      <!-- Proceso Selection Tab -->
      <tab-content
        :title="$t('balanceFormWizard.procesoSelection.title')"
        :before-change="validationForm"
        class="tab-enhanced"
      >
        <validation-observer
          ref="infoRules"
          tag="form"
        >
          <div class="tab-content-enhanced">
            <div class="section-card">
              <div class="section-header">
                <div class="section-icon">
                  <feather-icon icon="SettingsIcon" size="28" />
                </div>
                <div class="section-info">
                  <h4 class="section-title">{{ $t('balanceFormWizard.procesoSelection.systemTitle') }}</h4>
                  <p class="section-description">{{ $t('balanceFormWizard.procesoSelection.subtitle') }}</p>
                </div>
              </div>

              <div class="form-content">
                <validation-provider
                  #default="{ errors }"
                  :name="$t('balanceFormWizard.procesoSelection.name')"
                  rules="required"
                >
                  <b-form-group
                    :label="$t('balanceFormWizard.procesoSelection.label')"
                    label-for="proceso"
                    :state="errors.length > 0 ? false:null"
                    class="form-group-enhanced"
                  >
                    <b-form-select
                      v-model="proceso"
                      :options="procesos"
                      class="form-select-enhanced"
                      :class="{ 'is-invalid': errors.length > 0 }"
                    />
                    <b-form-invalid-feedback :state="errors.length > 0 ? false:null">
                      {{ errors[0] }}
                    </b-form-invalid-feedback>
                  </b-form-group>
                </validation-provider>
              </div>
            </div>
          </div>
        </validation-observer>
      </tab-content>

      <!-- Balances Tab -->
      <tab-content
        :title="$t('balanceFormWizard.balances.title')"
        class="tab-enhanced"
      >
        <div class="tab-content-enhanced">
          <div class="section-card">
            <div class="section-header">
              <div class="section-icon">
                <feather-icon icon="BarChart2Icon" size="28" />
              </div>
              <div class="section-info">
                <h4 class="section-title">{{ $t('balanceFormWizard.balances.configTitle') }}</h4>
                <p class="section-description">{{ $t('balanceFormWizard.balances.configSubtitle') }}</p>
              </div>
            </div>

            <div class="balance-content">
              <balance v-if="true" ref="balances_ref" :proceso="proceso" :show_tables="show_tables"/>
            </div>
          </div>
        </div>
      </tab-content>
    </form-wizard>

    <!-- Save Balance Modal -->
    <b-modal
      id="modal_guardar_balance"
      ref="my-modal"
      :title="$t('balanceFormWizard.saveModal.title')"
      @show="resetModal"
      @hidden="resetModal"
      @ok="handleOk"
      class="modal-enhanced"
      size="lg"
      centered
    >
      <div class="modal-content-enhanced">
        <div class="modal-header-custom">
          <div class="modal-icon">
            <feather-icon icon="SaveIcon" size="32" />
          </div>
          <h5 class="modal-title">{{ $t('balanceFormWizard.saveModal.title') }}</h5>
          <p class="modal-subtitle">{{ $t('balanceFormWizard.saveModal.subtitle') }}</p>
        </div>

        <form ref="form" @submit.stop.prevent="handleSubmit">
          <b-form-group
            :label="$t('balanceFormWizard.saveModal.label')"
            label-for="nombre_balance"
            :invalid-feedback="$t('balanceFormWizard.saveModal.invalidFeedback')"
            :state="nameState"
            class="form-group-enhanced"
          >
            <b-form-input
              id="nombre_balance"
              v-model="nombre_balance"
              :state="nameState"
              required
              class="form-input-enhanced"
              :placeholder="$t('balanceFormWizard.saveModal.placeholder')"
            />
          </b-form-group>
        </form>
      </div>

      <template #modal-footer="{ ok, cancel }">
        <div class="modal-footer-enhanced">
          <b-button variant="outline-secondary" @click="cancel" class="btn-enhanced">
            <feather-icon icon="XIcon" size="16" class="mr-2" />
            {{ $t('balanceFormWizard.saveModal.cancel') }}
          </b-button>
          <b-button variant="primary" @click="ok" :disabled="!nombre_balance.trim()" class="btn-enhanced">
            <feather-icon icon="SaveIcon" size="16" class="mr-2" />
            {{ $t('balanceFormWizard.saveModal.save') }}
          </b-button>
        </div>
      </template>
    </b-modal>
  </div>
</template>

<style lang="scss">
@import '~@core/scss/vue/libs/vue-wizard.scss';
@import '~@core/scss/vue/libs/vue-select.scss';

.balance-form-container {
  max-width: 1200px;
  margin: 0 auto;
  padding: 2rem;
  background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);
  transition: all 0.3s ease;

  // Dark mode support
  .dark-layout & {
    background: linear-gradient(135deg, #161d31 0%, #283046 100%);
    color: #b4b7bd;
  }

  .form-header {
    background: white;
    border-radius: 16px;
    padding: 2.5rem;
    margin-bottom: 2rem;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
    border: 1px solid #e9ecef;
    transition: all 0.3s ease;

    .dark-layout & {
      background: #283046;
      border-color: #3b4253;
      box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
    }

    .header-content {
      display: flex;
      align-items: center;
      text-align: center;
      justify-content: center;
      gap: 1.5rem;

      .header-icon {
        width: 80px;
        height: 80px;
        background: linear-gradient(135deg, #7367F0 0%, #9c88ff 100%);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        box-shadow: 0 8px 25px rgba(115, 103, 240, 0.3);

        svg {
          color: white;
          width: 32px;
          height: 32px;
        }
      }

      .header-text {
        .main-title {
          color: #2c3e50;
          font-weight: 700;
          font-size: 2.5rem;
          margin-bottom: 0.5rem;
          transition: color 0.3s ease;

          .dark-layout & {
            color: #d0d2d6;
          }
        }

        .subtitle {
          color: #6c757d;
          font-size: 1.1rem;
          margin: 0;
          max-width: 500px;
          transition: color 0.3s ease;

          .dark-layout & {
            color: #676d7d;
          }
        }
      }
    }
  }

  .loading-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(255, 255, 255, 0.95);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 9999;
    transition: background 0.3s ease;

    .dark-layout & {
      background: rgba(22, 29, 49, 0.95);
    }

    .loading-card {
      background: white;
      padding: 3rem;
      border-radius: 16px;
      box-shadow: 0 10px 40px rgba(0, 0, 0, 0.15);
      text-align: center;
      border: 1px solid #e9ecef;
      transition: all 0.3s ease;

      .dark-layout & {
        background: #283046;
        border-color: #3b4253;
        box-shadow: 0 10px 40px rgba(0, 0, 0, 0.4);
      }

      .loading-animation {
        margin-bottom: 2rem;

        .loading-spinner {
          width: 60px;
          height: 60px;
          position: relative;
          margin: 0 auto;

          .spinner-ring {
            position: absolute;
            width: 100%;
            height: 100%;
            border: 4px solid transparent;
            border-top-color: #7367F0;
            border-radius: 50%;
            animation: spin 1.2s cubic-bezier(0.5, 0, 0.5, 1) infinite;

            &:nth-child(1) {
              border-top-color: #7367F0;
            }
            &:nth-child(2) {
              border-top-color: #9c88ff;
            }
            &:nth-child(3) {
              border-top-color: #6c757d;
            }
          }
        }
      }

      .loading-content {
        margin-bottom: 2rem;

        .loading-title {
          color: #2c3e50;
          font-weight: 700;
          font-size: 1.8rem;
          margin-bottom: 0.5rem;
          transition: color 0.3s ease;

          .dark-layout & {
            color: #d0d2d6;
          }
        }

        .loading-subtitle {
          color: #6c757d;
          font-size: 1.1rem;
          margin: 0;
          max-width: 500px;
          transition: color 0.3s ease;

          .dark-layout & {
            color: #676d7d;
          }
        }

        .loading-progress {
          .progress-bar {
            width: 100%;
            height: 8px;
            background-color: #e9ecef;
            border-radius: 4px;
            overflow: hidden;

            .dark-layout & {
              background-color: #3b4253;
            }

            .progress-fill {
              height: 100%;
              background: linear-gradient(135deg, #7367F0 0%, #9c88ff 100%);
              border-radius: 4px;
              width: 0%;
              animation: fillProgress 2s ease-in-out forwards;
            }
          }

          .progress-text {
            color: #6c757d;
            font-size: 0.9rem;
            margin-top: 0.5rem;
            transition: color 0.3s ease;

            .dark-layout & {
              color: #676d7d;
            }
          }
        }
      }

      .loading-footer {
        display: flex;
        align-items: center;
        justify-content: center;
        color: #6c757d;
        font-size: 0.9rem;
        transition: color 0.3s ease;

        .dark-layout & {
          color: #676d7d;
        }

        svg {
          color: #7367F0;
          margin-right: 0.5rem;
        }
      }
    }
  }

  .wizard-enhanced {
    .vue-form-wizard {
      background: white;
      border-radius: 16px;
      box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
      border: 1px solid #e9ecef;
      transition: all 0.3s ease;

      .dark-layout & {
        background: #283046;
        border-color: #3b4253;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
      }

      .wizard-header {
        background: linear-gradient(135deg, #7367F0 0%, #9c88ff 100%);
        padding: 2rem;
        margin-bottom: 0;

        .wizard-title {
          color: white;
          font-size: 1.8rem;
          font-weight: 600;
        }

        .wizard-subtitle {
          color: rgba(255, 255, 255, 0.9);
          margin: 0;
        }
      }

      .wizard-content {
        padding: 0;
        background: transparent;
      }

      .wizard-footer {
        background: #f8f9fa;
        padding: 2rem;
        border-top: 1px solid #e9ecef;
        transition: all 0.3s ease;

        .dark-layout & {
          background: #1e232f;
          border-top-color: #3b4253;
        }

        .wizard-btn {
          border-radius: 10px;
          font-weight: 600;
          padding: 0.875rem 2rem;
          font-size: 1rem;
          transition: all 0.3s ease;
          border: none;

          &.wizard-btn-primary {
            background: linear-gradient(135deg, #7367F0 0%, #9c88ff 100%);
            color: white;

            &:hover {
              transform: translateY(-2px);
              box-shadow: 0 8px 25px rgba(115, 103, 240, 0.4);
            }
          }

          &.wizard-btn-secondary {
            background: #6c757d;
            color: white;

            &:hover {
              background: #5a6268;
              transform: translateY(-2px);
            }
          }
        }
      }
    }
  }

  .tab-enhanced {
    .tab-content-enhanced {
      padding: 2rem;

      .section-card {
        background: white;
        border-radius: 12px;
        padding: 2rem;
        box-shadow: 0 2px 12px rgba(0, 0, 0, 0.05);
        border: 1px solid #e9ecef;
        transition: all 0.3s ease;

        .dark-layout & {
          background: #283046;
          border-color: #3b4253;
          box-shadow: 0 2px 12px rgba(0, 0, 0, 0.2);
        }

        .section-header {
          display: flex;
          align-items: center;
          margin-bottom: 2rem;
          padding-bottom: 1.5rem;
          border-bottom: 2px solid #f8f9fa;
          transition: border-color 0.3s ease;

          .dark-layout & {
            border-bottom-color: #3b4253;
          }

          .section-icon {
            width: 70px;
            height: 70px;
            background: linear-gradient(135deg, #7367F0 0%, #9c88ff 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 1.5rem;
            box-shadow: 0 6px 20px rgba(115, 103, 240, 0.3);

            svg {
              color: white;
              width: 28px;
              height: 28px;
            }
          }

          .section-info {
            flex: 1;

            .section-title {
              color: #2c3e50;
              font-weight: 700;
              font-size: 1.5rem;
              margin-bottom: 0.5rem;
              transition: color 0.3s ease;

              .dark-layout & {
                color: #d0d2d6;
              }
            }

            .section-description {
              color: #6c757d;
              font-size: 1.1rem;
              line-height: 1.6;
              margin: 0;
              transition: color 0.3s ease;

              .dark-layout & {
                color: #676d7d;
              }
            }
          }
        }

        .form-content, .balance-content {
          .form-group-enhanced {
            margin-bottom: 2rem;

            label {
              font-weight: 600;
              color: #495057;
              margin-bottom: 0.75rem;
              font-size: 1.1rem;
              transition: color 0.3s ease;

              .dark-layout & {
                color: #d0d2d6;
              }
            }

            .form-select-enhanced {
              border-radius: 10px;
              border: 2px solid #e9ecef;
              padding: 1rem 1.25rem;
              font-size: 1rem;
              transition: all 0.3s ease;
              background: #ffffff;
              color: #495057;
              height: auto;
              min-height: 48px;
              line-height: 1.5;

              .dark-layout & {
                background: #283046;
                border-color: #404656;
                color: #b4b7bd;
              }

              &:focus {
                border-color: #7367F0;
                box-shadow: 0 0 0 0.2rem rgba(115, 103, 240, 0.25);
                background: white;
                color: #495057;

                .dark-layout & {
                  background: #283046;
                  color: #b4b7bd;
                }
              }

              &.is-invalid {
                border-color: #dc3545;
                box-shadow: 0 0 0 0.2rem rgba(220, 53, 69, 0.25);
                color: #495057;

                .dark-layout & {
                  color: #b4b7bd;
                }
              }

              option {
                color: #495057;
                background: white;
                padding: 8px 12px;

                .dark-layout & {
                  color: #b4b7bd;
                  background: #283046;
                }
              }
            }
          }
        }
      }
    }
  }

  .modal-enhanced {
    .modal-content {
      border-radius: 16px;
      border: none;
      box-shadow: 0 10px 40px rgba(0, 0, 0, 0.15);
      transition: all 0.3s ease;

      .dark-layout & {
        background: #283046;
        border-color: #3b4253;
        box-shadow: 0 10px 40px rgba(0, 0, 0, 0.4);
      }
    }

    .modal-header {
      background: linear-gradient(135deg, #7367F0 0%, #9c88ff 100%);
      color: white;
      border-radius: 16px 16px 0 0;
      border-bottom: none;
      padding: 2rem;

      .modal-title {
        font-weight: 700;
        font-size: 1.5rem;
      }
    }

    .modal-body {
      padding: 2rem;
      transition: background 0.3s ease;

      .dark-layout & {
        background: #283046;
      }
    }

    .modal-footer {
      border-top: 1px solid #e9ecef;
      padding: 2rem;
      background: #f8f9fa;
      border-radius: 0 0 16px 16px;
      transition: all 0.3s ease;

      .dark-layout & {
        background: #1e232f;
        border-top-color: #3b4253;
      }
    }
  }

  .modal-content-enhanced {
    .modal-header-custom {
      text-align: center;
      margin-bottom: 2rem;

      .modal-icon {
        width: 80px;
        height: 80px;
        background: linear-gradient(135deg, #7367F0 0%, #9c88ff 100%);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 1.5rem;
        box-shadow: 0 8px 25px rgba(115, 103, 240, 0.3);

        svg {
          color: white;
          width: 32px;
          height: 32px;
        }
      }

      .modal-title {
        color: #2c3e50;
        font-weight: 700;
        font-size: 1.5rem;
        margin-bottom: 0.5rem;
        transition: color 0.3s ease;

        .dark-layout & {
          color: #d0d2d6;
        }
      }

      .modal-subtitle {
        color: #6c757d;
        font-size: 1rem;
        margin: 0;
        transition: color 0.3s ease;

        .dark-layout & {
          color: #676d7d;
        }
      }
    }

    .form-group-enhanced {
      .form-input-enhanced {
        border-radius: 10px;
        border: 2px solid #e9ecef;
        padding: 1rem 1.25rem;
        font-size: 1rem;
        transition: all 0.3s ease;
        background: #ffffff;
        color: #495057;

        .dark-layout & {
          background: #283046;
          border-color: #404656;
          color: #b4b7bd;
        }

        &:focus {
          border-color: #7367F0;
          box-shadow: 0 0 0 0.2rem rgba(115, 103, 240, 0.25);
          background: white;
          color: #495057;

          .dark-layout & {
            background: #283046;
            color: #b4b7bd;
          }
        }

        &::placeholder {
          color: #adb5bd;

          .dark-layout & {
            color: #676d7d;
          }
        }

        &::selection {
          background: #7367F0;
          color: white;
        }
      }
    }
  }

  .modal-footer-enhanced {
    display: flex;
    justify-content: space-between;
    gap: 1rem;

    .btn-enhanced {
      border-radius: 10px;
      font-weight: 600;
      padding: 0.875rem 2rem;
      font-size: 1rem;
      transition: all 0.3s ease;
      border: 2px solid transparent;

      &:hover {
        transform: translateY(-2px);
      }

      &.btn-outline-secondary {
        border-color: #6c757d;
        color: #6c757d;

        &:hover {
          background: #6c757d;
          border-color: #6c757d;
          color: white;
        }
      }

      &.btn-primary {
        background: linear-gradient(135deg, #7367F0 0%, #9c88ff 100%);
        border-color: #7367F0;

        &:hover {
          box-shadow: 0 8px 25px rgba(115, 103, 240, 0.4);
        }
      }
    }
  }
}

// Responsive design
@media (max-width: 768px) {
  .balance-form-container {
    padding: 1rem;

    .form-header {
      padding: 1.5rem;

      .header-content {
        flex-direction: column;
        gap: 1rem;

        .header-icon {
          width: 60px;
          height: 60px;

          i {
            font-size: 1.5rem;
          }
        }

        .header-text {
          .main-title {
            font-size: 2rem;
          }
        }
      }
    }

    .tab-enhanced {
      .tab-content-enhanced {
        padding: 1rem;

        .section-card {
          padding: 1.5rem;

          .section-header {
            flex-direction: column;
            text-align: center;

            .section-icon {
              margin-right: 0;
              margin-bottom: 1rem;
            }
          }
        }
      }
    }

    .modal-footer-enhanced {
      flex-direction: column;

      .btn-enhanced {
        width: 100%;
      }
    }
  }
}

// Animations
@keyframes fadeInUp {
  from {
    opacity: 0;
    transform: translateY(30px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

@keyframes spin {
  0% {
    transform: rotate(0deg);
  }
  100% {
    transform: rotate(360deg);
  }
}

@keyframes fillProgress {
  0% {
    width: 0%;
  }
  50% {
    width: 60%;
  }
  100% {
    width: 100%;
  }
}

.section-card {
  animation: fadeInUp 0.6s ease-out;
}

.loading-card {
  animation: fadeInUp 0.4s ease-out;
}

// Custom scrollbar
.wizard-enhanced {
  &::-webkit-scrollbar {
    width: 8px;
  }

  &::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 4px;
  }

  &::-webkit-scrollbar-thumb {
    background: #7367F0;
    border-radius: 4px;

    &:hover {
      background: #5a4fcf;
    }
  }
}

// Force scrolling for the entire page
html, body {
  overflow-y: auto !important;
  height: auto !important;
}

// Override any wizard CSS that might block scrolling
.vue-form-wizard {
  overflow: visible !important;
  height: auto !important;

  .wizard-content {
    overflow: visible !important;
    height: auto !important;
  }

  .wizard-footer {
    overflow: visible !important;
  }
}

// Ensure the main container allows scrolling
.balance-form-container {
  overflow: visible !important;
  height: auto !important;
}

// Fix Bootstrap Vue form inputs visibility
.form-control,
.form-select,
.b-form-input,
.b-form-select {
  color: #495057 !important;
  background-color: #ffffff !important;

  &:focus {
    color: #495057 !important;
    background-color: #ffffff !important;
  }

  &::placeholder {
    color: #adb5bd !important;
  }
}

// Dark mode support for form inputs
.dark-layout {
  .form-control,
  .form-select,
  .b-form-input,
  .b-form-select {
    color: #b4b7bd !important;
    background-color: #283046 !important;
    border-color: #404656 !important;

    &:focus {
      color: #b4b7bd !important;
      background-color: #283046 !important;
      border-color: #7367F0 !important;
    }

    &::placeholder {
      color: #676d7d !important;
    }
  }
}

// Specific fixes for select elements
.b-form-select,
.form-select {
  height: auto !important;
  min-height: 48px !important;
  line-height: 1.5 !important;
  padding: 12px 16px !important;
  color: #495057 !important;
  background-color: #ffffff !important;

  &:focus {
    color: #495057 !important;
    background-color: #ffffff !important;
  }

  option {
    color: #495057 !important;
    background-color: #ffffff !important;
    padding: 8px 12px !important;
  }
}

// Dark mode support for select elements
.dark-layout {
  .b-form-select,
  .form-select {
    color: #b4b7bd !important;
    background-color: #283046 !important;
    border-color: #404656 !important;

    &:focus {
      color: #b4b7bd !important;
      background-color: #283046 !important;
      border-color: #7367F0 !important;
    }

    option {
      color: #b4b7bd !important;
      background-color: #283046 !important;
      padding: 8px 12px !important;
    }
  }
}

// Ensure all form elements have proper contrast
input, select, textarea {
  color: #495057 !important;
  background-color: #ffffff !important;
}

// Dark mode support for all form elements
.dark-layout {
  input, select, textarea {
    color: #b4b7bd !important;
    background-color: #283046 !important;
  }
}
</style>

<script>
import { FormWizard, TabContent } from 'vue-form-wizard'
import { ValidationProvider, ValidationObserver } from 'vee-validate'
import ToastificationContent from '@core/components/toastification/ToastificationContent.vue'
import 'vue-form-wizard/dist/vue-form-wizard.min.css'
import axios from 'axios'
import Balance from './Balances.vue'
import {
  BRow,
  BCol,
  BFormGroup,
  BFormInput,
  BFormSelect,
  BFormInvalidFeedback,
  BSpinner,
  BModal,
  BButton,
} from 'bootstrap-vue'
import { required, email } from '@validations'
import Ripple from 'vue-ripple-directive'

export default {
  components: {
    BModal,
    BButton,
    Balance,
    ValidationProvider,
    ValidationObserver,
    FormWizard,
    TabContent,
    BRow,
    BCol,
    BFormGroup,
    BFormInput,
    BFormSelect,
    BFormInvalidFeedback,
    ToastificationContent,
    BSpinner,
  },
  directives: {
    Ripple,
  },
  data() {
    return {
      nombre_balance: '',
      nameState: null,
      required,
      email,
      valle: '',
      proceso: 0,
      valles: [],
      procesos: [],
      loading: false,
      new_balance: false,
      balance_id: null,
      show_tables: false,
    }
  },
  mounted() {
    console.log('mounted')
    console.log(this.valles)
    this.loading = true
    axios
      .get('getValles/1')
      .then(response => {
        console.log('response', response)
        // Agregar opción placeholder al inicio
        this.valles = [
          { value: '', text: 'Seleccione un valle' },
          ...response.data.valles,
        ]

        console.log('valles', this.valles)
      })
      .catch(e => {
        console.log('FAILURE!!', e)
      })
      .finally(() => {
        this.loading = false
      })
  },
  methods: {
    checkFormValidity() {
      const valid = this.$refs.form.checkValidity()
      this.nameState = valid
      return valid
    },
    resetModal() {
      this.nombre_balance = ''
      this.nameState = null
    },
    handleOk(bvModalEvent) {
      // Prevent modal from closing
      bvModalEvent.preventDefault()
      // Trigger submit handler
      this.handleSubmit()
    },
    handleSubmit() {
      // Exit when the form isn't valid
      if (!this.checkFormValidity()) {
        return
      }
      console.log('aqui se sube le nombre del balance', this.$refs.balances_ref.datos_entrada)
      axios
        .post('save_balance', {
          datos_entrada: this.$refs.balances_ref.datos_entrada,
          nombre_balance: this.nombre_balance,
          datos_entrada_id: this.$refs.balances_ref.datos_entrada_id,
          proceso_id: this.proceso,
        }, {
          headers: {
            'Content-Type': 'application/json',
          },
        })
        .then(response => {
          this.balance_id = response.data.balance_id
          this.$toast({
            component: ToastificationContent,
            props: {
              title: this.$t('balanceFormWizard.toast.success'),
              icon: 'EditIcon',
              variant: 'success',
            },
          })
          this.$refs['my-modal'].hide()
        })
        .catch(e => {
          console.log('FAILURE!! correr_balance', e)
          this.$toast({
            component: ToastificationContent,
            props: {
              title: this.$t('balanceFormWizard.toast.error'),
              icon: 'EditIcon',
              variant: 'danger',
            },
          })
          this.$refs['my-modal'].hide()
        })

      // Push the name to submitted names
      // this.submittedNames.push(this.nombre_balance)
      // Hide the modal manually
      this.$nextTick(() => {
        this.$bvModal.hide('modal-prevent-closing')
      })
    },

    create_new_balance() {
      this.new_balance = true
    },
    valles_change(value) {
      this.show_tables = false
      console.log('cambio en valles', value, this.valle)
      this.loading = true
      axios
        .get(`getProcesos/${this.valle}`)
        .then(response => {
          console.log('response', response)
          // Agregar opción placeholder al inicio
          this.procesos = [
            { value: '', text: 'Seleccione un proceso' },
            ...response.data.procesos,
          ]
          console.log('los procesos', this.procesos)
        })
        .catch(e => {
          console.log('FAILURE!!', e)
        })
        .finally(() => {
          this.loading = false
        })
    },
    formSubmitted() {
      this.$refs['my-modal'].show()
      // $bvModal.show('modal_guardar_balance')
    },
    validationForm() {
      return new Promise((resolve, reject) => {
        this.$refs.accountRules.validate().then(success => {
          if (success) {
            resolve(true)
          } else {
            reject()
          }
        })
      })
    },
  },
}
</script>
