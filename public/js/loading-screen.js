document.addEventListener('DOMContentLoaded', function () {
  const progressFill = document.querySelector('.progress-fill')
  const progressText = document.querySelector('.progress-text')
  const loadingBg = document.getElementById('loading-bg')

  if (!progressFill || !progressText || !loadingBg) {
    return
  }

  const messages = [
    'Inicializando...',
    'Cargando módulos...',
    'Conectando servicios...',
    'Preparando interfaz...',
    'Sistema listo!',
  ]

  let progress = 0
  let messageIndex = 0

  const interval = setInterval(() => {
    progress += Math.random() * 15
    if (progress > 100) progress = 100

    progressFill.style.width = `${progress}%`

    if (progress >= (messageIndex + 1) * 20 && messageIndex < messages.length - 1) {
      messageIndex += 1
      progressText.textContent = messages[messageIndex]
    }

    if (progress >= 100) {
      clearInterval(interval)
      setTimeout(() => {
        loadingBg.classList.add('fade-out')
        setTimeout(() => {
          loadingBg.style.display = 'none'
        }, 500)
      }, 1000)
    }
  }, 200)
})
