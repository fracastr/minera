// Client-side mirror of app/Rules/StrongPassword.php (same order and messages).
// The breached-password check (pwnedpasswords) only runs on the server.
const COMMON_PASSWORDS = [
  'password', 'password1', 'password12', 'password123', '12345678', '123456789', '1234567890',
  'qwerty123', 'admin123', 'admin1234', 'changeme', 'changeme123', 'welcome123', 'letmein123',
  'abc123456', 'test123456',
]

export const PASSWORD_MIN_LENGTH = 12

export const strongPasswordRule = value => {
  const v = value ?? ''
  if (v.length < PASSWORD_MIN_LENGTH) return `La contraseña debe tener al menos ${PASSWORD_MIN_LENGTH} caracteres.`
  if (!/[a-z]/.test(v)) return 'La contraseña debe incluir al menos una letra minúscula.'
  if (!/[A-Z]/.test(v)) return 'La contraseña debe incluir al menos una letra mayúscula.'
  if (!/\d/.test(v)) return 'La contraseña debe incluir al menos un número.'
  if (!/[^A-Za-z0-9]/.test(v)) return 'La contraseña debe incluir al menos un carácter especial.'
  if (COMMON_PASSWORDS.includes(v.toLowerCase())) return 'La contraseña es demasiado común. Elige una más segura.'

  return true
}

export const requiredRule = v => !!String(v ?? '').trim() || 'Este campo es obligatorio.'
export const emailRule = v => !v || /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v) || 'Ingresa un correo válido.'

// Same precedence as the old app: response message, then 422 field errors, then fallback.
export const apiErrorMessage = (e, fallback) => {
  if (e?.data?.message) return e.data.message
  if (e?.data?.errors) return Object.values(e.data.errors).flat().join(' ')

  return fallback
}
