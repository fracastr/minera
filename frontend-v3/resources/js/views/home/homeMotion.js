// Scroll and entrance motion for the public homepage, modeled on the Refero "Arva" reference:
// Lenis smooth scroll synced to GSAP ScrollTrigger, a preloader that slides away, SplitText
// line flips on the hero, a sticky media column that follows the workflow steps, and
// videos that only load and play while on screen.
import { gsap } from 'gsap'
import { ScrollTrigger } from 'gsap/ScrollTrigger'
import { SplitText } from 'gsap/SplitText'
import Lenis from 'lenis'

gsap.registerPlugin(ScrollTrigger, SplitText)

const NAV_HEIGHT = 72

/**
 * @param root homepage element
 * @param opts { reducedMotion, onNavSolid(bool), onStep(index) }
 * @returns cleanup function
 */
export default function initHomeMotion(root, { reducedMotion, onNavSolid, onStep }) {
  const cleanups = []
  // Lets home.scss undo the app-wide html/body overflow that breaks position: sticky.
  document.documentElement.classList.add('is-home')
  cleanups.push(() => document.documentElement.classList.remove('is-home'))
  const isPhone = window.matchMedia('(max-width: 767px)').matches
  const ctx = gsap.context(() => {}, root)
  cleanups.push(() => ctx.revert())

  // Header turns solid as soon as the page moves, like the reference.
  const updateNav = () => onNavSolid(window.scrollY > 5)
  updateNav()
  window.addEventListener('scroll', updateNav, { passive: true })
  cleanups.push(() => window.removeEventListener('scroll', updateNav))

  // Smooth scroll; native scroll stays when the visitor asks for less motion.
  let lenis = null
  if (!reducedMotion) {
    lenis = new Lenis({
      duration: isPhone ? 0.9 : 1.4,
      easing: t => Math.min(1, 1.001 - 2 ** (-10 * t)),
      smoothWheel: true,
    })
    const raf = time => lenis.raf(time * 1000)
    lenis.on('scroll', ScrollTrigger.update)
    gsap.ticker.add(raf)
    gsap.ticker.lagSmoothing(0)
    cleanups.push(() => {
      gsap.ticker.remove(raf)
      lenis.destroy()
    })
  }

  const scrollToTarget = (target, immediate) => {
    if (lenis) lenis.scrollTo(target, { offset: -NAV_HEIGHT, immediate })
    else window.scrollTo({ top: target.getBoundingClientRect().top + window.scrollY - NAV_HEIGHT, behavior: immediate ? 'auto' : 'smooth' })
  }

  // In-page links go through Lenis so they glide instead of jumping.
  const onAnchorClick = event => {
    const link = event.target.closest('a[href^="#"]')
    if (!link) return
    const target = root.querySelector(link.getAttribute('href'))
    if (!target) return
    event.preventDefault()
    scrollToTarget(target)
    window.history.replaceState(null, '', link.getAttribute('href'))
  }
  root.addEventListener('click', onAnchorClick)
  cleanups.push(() => root.removeEventListener('click', onAnchorClick))

  // vue-router doesn't run scrollBehavior on first load, so honor deep links like /#simulador.
  const deepLink = window.location.hash && root.querySelector(window.location.hash)

  // Videos: lazy ones get their src on first approach; everything pauses off screen.
  const videos = [...root.querySelectorAll('video')]
  const videoObserver = new IntersectionObserver(entries => {
    entries.forEach(({ target: video, isIntersecting }) => {
      if (isIntersecting && video.dataset.src && !video.getAttribute('src')) {
        video.setAttribute('src', video.dataset.src)
      }
      if (isIntersecting && !video.dataset.step) video.play().catch(() => {})
      else if (!isIntersecting) video.pause()
    })
  }, { rootMargin: '200px 0px' })
  if (!reducedMotion) {
    videos
      // Phones keep the full HD posters for everything but the hero, to save data.
      .filter(video => !(isPhone && video.dataset.src))
      .forEach(video => videoObserver.observe(video))
  }
  cleanups.push(() => videoObserver.disconnect())

  // Workflow: the step nearest the middle of the screen drives the sticky media column.
  const stepVideos = videos.filter(video => video.dataset.step)
  const setStep = index => {
    onStep(index)
    if (reducedMotion || isPhone) return
    stepVideos.forEach(video => {
      if (Number(video.dataset.step) === index) {
        if (!video.getAttribute('src')) video.setAttribute('src', video.dataset.src)
        video.play().catch(() => {})
      } else {
        video.pause()
      }
    })
  }
  ctx.add(() => {
    root.querySelectorAll('[data-step-trigger]').forEach((el, index) => {
      ScrollTrigger.create({
        trigger: el,
        start: 'top 60%',
        end: 'bottom 60%',
        onToggle: self => self.isActive && setStep(index),
      })
    })
  })

  // Reveals use one IntersectionObserver instead of a trigger per element.
  const revealEls = [...root.querySelectorAll('[data-reveal]')]
  const revealObserver = new IntersectionObserver(entries => {
    entries.forEach(({ target, isIntersecting }) => {
      if (!isIntersecting) return
      gsap.to(target, {
        opacity: 1, y: 0, duration: 1.1, ease: 'power3.out', clearProps: 'transform',
      })
      revealObserver.unobserve(target)
    })
  }, { rootMargin: '0px 0px -12% 0px' })
  if (!reducedMotion) {
    gsap.set(revealEls, { opacity: 0, y: 32 })
    revealEls.forEach(el => revealObserver.observe(el))
  }
  cleanups.push(() => revealObserver.disconnect())

  // Hero video drifts a little as the hero leaves, for depth.
  if (!reducedMotion) {
    ctx.add(() => {
      gsap.to(root.querySelector('.home-hero__video'), {
        scale: 1.08,
        yPercent: 6,
        ease: 'none',
        scrollTrigger: {
          trigger: root.querySelector('.home-hero'), start: 'top top', end: 'bottom top', scrub: true,
        },
      })
    })
  }

  // Entrance: the preloader lifts, then the headline flips in line by line and the buttons follow.
  const loader = root.querySelector('.home-loader')
  const intro = () => {
    if (reducedMotion) return
    ctx.add(() => {
      const split = new SplitText(root.querySelector('.home-hero__title'), { type: 'lines', linesClass: 'home-hero__line' })
      const lede = new SplitText(root.querySelector('.home-hero__lede'), { type: 'lines' })
      gsap.timeline()
        .from(split.lines, {
          opacity: 0, rotationX: -40, yPercent: 30, transformOrigin: 'top center -50', duration: 0.9, ease: 'power3.out', stagger: 0.18,
        })
        .from(lede.lines, {
          opacity: 0, rotationX: -30, transformOrigin: 'top center -50', duration: 0.6, ease: 'power2.out', stagger: 0.12,
        }, '-=0.45')
        .from(root.querySelectorAll('.home-hero__actions > *'), {
          opacity: 0, rotationX: -30, transformOrigin: 'top center -50', duration: 0.5, stagger: 0.18, clearProps: 'transform',
        }, '-=0.3')
        .from(root.querySelectorAll('.home-hero__cue, .home-hero__flow'), { opacity: 0, duration: 0.8 }, '-=0.2')
    })
  }

  const finishLoad = () => {
    if (deepLink) scrollToTarget(deepLink, true)
    ScrollTrigger.refresh()
  }

  if (reducedMotion || !loader) {
    if (loader) loader.remove()
    finishLoad()
  } else {
    // Wait for the hero video (or give up after a beat), but stay at least 400ms to avoid a flash.
    const started = Date.now()
    let done = false
    const exit = () => {
      if (done) return
      done = true
      setTimeout(() => {
        gsap.to(loader, {
          yPercent: -100,
          duration: 0.9,
          ease: 'power2.inOut',
          onComplete: () => loader.remove(),
        })
        intro()
        finishLoad()
      }, Math.max(0, 400 - (Date.now() - started)))
    }
    const heroVideo = root.querySelector('.home-hero__video')
    if (heroVideo && heroVideo.readyState < 3) heroVideo.addEventListener('canplay', exit, { once: true })
    else exit()
    const cap = setTimeout(exit, 1200)
    cleanups.push(() => clearTimeout(cap))
  }

  return () => cleanups.reverse().forEach(fn => fn())
}
