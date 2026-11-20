// Illustrative two-product reconciliation (feed = concentrate + tails) for the homepage demo.
// shortcut: two-stage weighted least squares (mass, then fines) with closed-form projection;
// Sondek's real engine solves the full network with bounds in the Python/GEKKO service.

const STREAMS = ['feed', 'conc', 'tails']

// Spread the closure error r = in - out across streams in proportion to their variances.
function project(values, sigmas) {
  const r = values.feed - values.conc - values.tails
  const v = STREAMS.reduce((acc, s) => ({ ...acc, [s]: sigmas[s] ** 2 }), {})
  const total = v.feed + v.conc + v.tails
  return {
    feed: values.feed - (r * v.feed) / total,
    conc: values.conc + (r * v.conc) / total,
    tails: values.tails + (r * v.tails) / total,
  }
}

/**
 * @param measured { feed|conc|tails: { mass, grade } } mass in t, grade in %Fe
 * @param tolerance { feed|conc|tails: number } relative tolerance in %
 */
export default function reconcile(measured, tolerance) {
  const mass = STREAMS.reduce((acc, s) => ({ ...acc, [s]: measured[s].mass }), {})
  const massSigma = STREAMS.reduce((acc, s) => ({ ...acc, [s]: (mass[s] * tolerance[s]) / 100 }), {})
  const mHat = project(mass, massSigma)

  const fines = STREAMS.reduce((acc, s) => ({ ...acc, [s]: (mHat[s] * measured[s].grade) / 100 }), {})
  const finesSigma = STREAMS.reduce((acc, s) => ({ ...acc, [s]: (fines[s] * tolerance[s]) / 100 }), {})
  const fHat = project(fines, finesSigma)

  const streams = STREAMS.reduce((acc, s) => ({
    ...acc,
    [s]: { mass: mHat[s], grade: (fHat[s] / mHat[s]) * 100, fines: fHat[s] },
  }), {})

  const measuredFines = s => (measured[s].mass * measured[s].grade) / 100
  return {
    streams,
    closure: {
      mass: ((mass.feed - mass.conc - mass.tails) / mass.feed) * 100,
      fines: ((measuredFines('feed') - measuredFines('conc') - measuredFines('tails')) / measuredFines('feed')) * 100,
    },
    recovery: (fHat.conc / fHat.feed) * 100,
    yield: (mHat.conc / mHat.feed) * 100,
  }
}
