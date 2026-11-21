import ElkConstructor, { type ELK } from 'elkjs/lib/elk-api'
import ElkWorker from 'elkjs/lib/elk-worker.min.js?worker'

// One ELK Web Worker shared by the mounted diagrams: created on first use, terminated when the last one unmounts.
let elk: ELK | null = null
let users = 0

export const getElk = () => (elk ??= new ElkConstructor({ workerFactory: () => new ElkWorker() }))

export function acquireElk() {
  users++
}

export function releaseElk() {
  users = Math.max(0, users - 1)
  if (!users) dropElk()
}

/** Terminates the worker (after a crash, or when nobody uses it); the next getElk() starts a fresh one. */
export function dropElk() {
  elk?.terminateWorker()
  elk = null
}
