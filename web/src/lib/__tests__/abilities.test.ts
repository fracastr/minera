import { describe, expect, it } from 'vitest'
import { can } from '../abilities'

const admin = [{ action: 'manage', subject: 'all' }]
const operator = [
  { action: 'read', subject: 'Balances' },
  { action: 'create', subject: 'Balances' },
  { action: 'update', subject: 'Balances' },
  { action: 'read', subject: 'Auth' },
]
const viewer = [{ action: 'read', subject: 'Balances' }, { action: 'read', subject: 'Auth' }]

describe('can', () => {
  it('lets admin do anything', () => {
    expect(can(admin, 'manage', 'Users')).toBe(true)
    expect(can(admin, 'read', 'DashboardAnalytics')).toBe(true)
  })
  it('limits operator to balances', () => {
    expect(can(operator, 'create', 'Balances')).toBe(true)
    expect(can(operator, 'manage', 'Users')).toBe(false)
    expect(can(operator, 'read', 'DashboardAnalytics')).toBe(false)
  })
  it('keeps viewer read-only', () => {
    expect(can(viewer, 'read', 'Balances')).toBe(true)
    expect(can(viewer, 'create', 'Balances')).toBe(false)
  })
  it('honours dashboard_access', () => {
    expect(can([...viewer, { action: 'read', subject: 'DashboardAnalytics' }], 'read', 'DashboardAnalytics')).toBe(true)
  })
  it('denies without abilities', () => {
    expect(can(undefined, 'read', 'Balances')).toBe(false)
    expect(can([], 'read', 'Auth')).toBe(false)
  })
})
