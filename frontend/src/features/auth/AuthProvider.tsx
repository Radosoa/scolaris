import { useQuery, useQueryClient } from '@tanstack/react-query'
import { useCallback, useEffect, useMemo, useState, type ReactNode } from 'react'
import { UNAUTHORIZED_EVENT } from '@/lib/api'
import { tokenStorage } from '@/lib/token-storage'
import type { LoginPayload, Role } from '@/types/auth'
import * as authApi from './api'
import { AuthContext, ME_QUERY_KEY, type AuthContextValue } from './auth-context'

export function AuthProvider({ children }: { children: ReactNode }) {
  const queryClient = useQueryClient()
  const [token, setToken] = useState<string | null>(() => tokenStorage.get())

  const meQuery = useQuery({
    queryKey: ME_QUERY_KEY,
    queryFn: authApi.fetchMe,
    enabled: token !== null,
    staleTime: 5 * 60_000,
  })

  const resetSession = useCallback(() => {
    tokenStorage.clear()
    setToken(null)
    queryClient.clear()
  }, [queryClient])

  // Token expiré ou révoqué côté serveur : l'intercepteur Axios émet cet événement
  useEffect(() => {
    window.addEventListener(UNAUTHORIZED_EVENT, resetSession)
    return () => window.removeEventListener(UNAUTHORIZED_EVENT, resetSession)
  }, [resetSession])

  const login = useCallback(
    async (payload: LoginPayload) => {
      const { token: nouveauToken, user } = await authApi.login(payload)
      tokenStorage.set(nouveauToken)
      queryClient.setQueryData(ME_QUERY_KEY, user)
      setToken(nouveauToken)
      return user
    },
    [queryClient],
  )

  const logout = useCallback(async () => {
    try {
      await authApi.logout()
    } finally {
      resetSession()
    }
  }, [resetSession])

  const user = token ? (meQuery.data ?? null) : null

  const value = useMemo<AuthContextValue>(
    () => ({
      status: user ? 'authenticated' : token && meQuery.isPending ? 'loading' : 'guest',
      user,
      login,
      logout,
      hasRole: (...roles: Role[]) => !!user && roles.some((role) => user.roles.includes(role)),
    }),
    [user, token, meQuery.isPending, login, logout],
  )

  return <AuthContext value={value}>{children}</AuthContext>
}
