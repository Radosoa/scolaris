import { QueryClientProvider } from '@tanstack/react-query'
import { ThemeProvider } from '@/components/ThemeProvider'
import { RouterProvider } from 'react-router'
import { Toaster } from '@/components/ui/sonner'
import { TooltipProvider } from '@/components/ui/tooltip'
import { AuthProvider } from '@/features/auth/AuthProvider'
import { queryClient } from '@/lib/query-client'
import { router } from '@/routes/router'

export default function App() {
  return (
    // Thème clair, sombre ou celui du système ; choix mémorisé (voir aussi le script d'index.html)
    <ThemeProvider>
      <QueryClientProvider client={queryClient}>
        <AuthProvider>
          <TooltipProvider>
            <RouterProvider router={router} />
            <Toaster richColors position="top-right" />
          </TooltipProvider>
        </AuthProvider>
      </QueryClientProvider>
    </ThemeProvider>
  )
}
