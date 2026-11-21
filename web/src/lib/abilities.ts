export interface Ability {
  action: string
  subject: string
}

/** Same CASL-style rules the server sends in userData.ability ("manage" and "all" are wildcards). */
export function can(abilities: readonly Ability[] | undefined, action: string, subject: string): boolean {
  return (abilities ?? []).some(
    a => (a.action === 'manage' || a.action === action) && (a.subject === 'all' || a.subject === subject),
  )
}
