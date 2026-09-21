import api from '@/lib/api'
import toast from 'react-hot-toast'

/**
 * Télécharge un fichier depuis une route API authentifiée (Bearer token).
 * Un simple <a href="..."> ne fonctionne pas ici : l'API n'accepte que
 * l'en-tête Authorization, jamais de session cookie.
 */
export async function downloadFile(url: string, params: Record<string, any> = {}, fallbackName = 'export') {
  try {
    const res = await api.get(url, { params, responseType: 'blob' })

    const disposition = res.headers['content-disposition'] as string | undefined
    const match = disposition?.match(/filename="?([^"]+)"?/)
    const filename = match?.[1] ?? fallbackName

    const blobUrl = window.URL.createObjectURL(res.data)
    const link = document.createElement('a')
    link.href = blobUrl
    link.download = filename
    document.body.appendChild(link)
    link.click()
    link.remove()
    window.URL.revokeObjectURL(blobUrl)
  } catch {
    toast.error("Échec de l'export.")
  }
}
