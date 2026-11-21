import ElkConstructor, { type ELK } from 'elkjs/lib/elk-api'
import ElkWorker from 'elkjs/lib/elk-worker.min.js?worker'

let elk: ELK | null = null

/** One ELK instance per tab, laying out in a Web Worker so the main thread stays free. */
export const getElk = () => (elk ??= new ElkConstructor({ workerFactory: () => new ElkWorker() }))
