// Mirrors app/Rules/StrongPassword.php (the server also rejects common and breached passwords).
export const passwordRules = [
  { label: 'Al menos 12 caracteres', test: (p: string) => p.length >= 12 },
  { label: 'Una letra mayúscula', test: (p: string) => /[A-Z]/.test(p) },
  { label: 'Una letra minúscula', test: (p: string) => /[a-z]/.test(p) },
  { label: 'Un número', test: (p: string) => /\d/.test(p) },
  { label: 'Un carácter especial', test: (p: string) => /[^A-Za-z0-9]/.test(p) },
]
