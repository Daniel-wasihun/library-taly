/**
 * UniLibrary PWA & Universal File Downloader
 * --------------------------------------------------------------------------
 * Handles file saving and exporting with support for:
 * 1. File System Access API (showSaveFilePicker) -> Lets user pick directory
 * 2. Web Share API (navigator.share) -> Lets mobile PWA user Save to Files / Downloads
 * 3. Robust Blob anchor download with extended URL lifetime (prevents immediate revoke)
 */

export interface SaveFileOptions {
  blob: Blob;
  filename: string;
  mimeType?: string;
  title?: string;
}

export interface SaveFileResult {
  success: boolean;
  method: 'picker' | 'share' | 'download' | 'cancelled';
  error?: any;
}

export async function saveFileWithPickerOrFallback(options: SaveFileOptions): Promise<SaveFileResult> {
  const { blob, filename, mimeType = 'application/octet-stream', title = filename } = options;

  // 1. Desktop File System Access API: Let user choose any folder/directory
  if (typeof window !== 'undefined' && 'showSaveFilePicker' in window) {
    try {
      const ext = filename.split('.').pop() || '';
      const pickerOptions: any = {
        suggestedName: filename,
        types: [
          {
            description: `${ext.toUpperCase()} Document`,
            accept: {
              [mimeType]: [`.${ext}`],
            },
          },
        ],
      };

      const handle = await (window as any).showSaveFilePicker(pickerOptions);
      const writable = await handle.createWritable();
      await writable.write(blob);
      await writable.close();
      return { success: true, method: 'picker' };
    } catch (err: any) {
      if (err.name === 'AbortError') {
        return { success: false, method: 'cancelled' };
      }
      console.warn('[FileDownloader] showSaveFilePicker failed, trying fallback:', err);
    }
  }

  // 2. Mobile PWA Web Share API: Let user save to Files, Downloads, or share to other apps
  const isMobile = typeof window !== 'undefined' && /android|iphone|ipad|ipod/i.test(navigator.userAgent);
  if (isMobile && typeof navigator !== 'undefined' && 'canShare' in navigator && 'share' in navigator) {
    try {
      const file = new File([blob], filename, { type: mimeType });
      if (navigator.canShare({ files: [file] })) {
        await navigator.share({
          files: [file],
          title: title,
          text: `Woldia University Library - ${filename}`,
        });
        return { success: true, method: 'share' };
      }
    } catch (err: any) {
      if (err.name === 'AbortError') {
        return { success: false, method: 'cancelled' };
      }
      console.warn('[FileDownloader] Web Share API failed, trying fallback:', err);
    }
  }

  // 3. Robust Blob URL Fallback with Extended Lifetime (2 minutes)
  try {
    const objectUrl = window.URL.createObjectURL(blob);
    const anchor = document.createElement('a');
    anchor.href = objectUrl;
    anchor.download = filename;
    anchor.style.display = 'none';
    anchor.target = '_blank';
    anchor.rel = 'noopener noreferrer';
    document.body.appendChild(anchor);
    anchor.click();

    // Do NOT immediately revoke: keep alive for 120s so browser/PWA completes saving
    setTimeout(() => {
      try {
        if (anchor.parentNode) {
          anchor.parentNode.removeChild(anchor);
        }
        window.URL.revokeObjectURL(objectUrl);
      } catch {}
    }, 120000);

    return { success: true, method: 'download' };
  } catch (err: any) {
    console.error('[FileDownloader] Blob download error:', err);
    return { success: false, method: 'download', error: err };
  }
}
