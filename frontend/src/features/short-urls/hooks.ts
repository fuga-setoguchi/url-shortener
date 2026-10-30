import { useMutation, useQueryClient } from '@tanstack/react-query'
import { ApiError, apiClient, type ShortUrl } from '../../api/client.ts'

export const shortUrlKeys = {
  all: ['short-urls'] as const,
}

/**
 * 短縮URLを登録する（API-01）。
 */
export function useCreateShortUrl() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: async (originalUrl: string): Promise<ShortUrl> => {
      const { data, error, response } = await apiClient.POST('/short-urls', {
        body: { original_url: originalUrl },
      })
      if (data !== undefined) {
        return data.data
      }
      // 検証エラーの本文の形が保証されるのは 422 のときだけ
      throw new ApiError(
        response.status,
        response.status === 422 ? error.errors : undefined,
      )
    },
    // 一覧に新しいURLが表示されるよう、一覧のキャッシュを無効化する
    onSuccess: () =>
      queryClient.invalidateQueries({ queryKey: shortUrlKeys.all }),
  })
}
