/* eslint-disable global-require */

export const miningHeroes = [
  {
    src: require('./cmp1.jpg'),
    credit: 'Operación CMP',
  },
  {
    src: require('./cmp2.jpg'),
    credit: 'Operación CMP',
  },
  {
    src: require('./mining-los-colorados.jpg'),
    credit: 'Mina Los Colorados · Guillermo Andre · CC BY 2.0',
  },
  {
    src: require('./mining-rajo-norte.jpg'),
    credit: 'Rajo en el norte de Chile · Chris Hunkeler · CC BY-SA 2.0',
  },
  {
    src: require('./mining-atacama-rajo.jpg'),
    credit: 'Rajo en el Desierto de Atacama · NASA · dominio público',
  },
  {
    src: require('./mining-centinela.jpg'),
    credit: 'Distrito Centinela · Sentinel Hub · CC BY-SA 2.0',
  },
]

export function pickMiningHero() {
  return miningHeroes[Math.floor(Math.random() * miningHeroes.length)]
}
