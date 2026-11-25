/**
 * UniLibrary PWA & Universal File Downloader
 * --------------------------------------------------------------------------
 * Handles file saving and exporting with support for:
 * 1. Desktop File System Access API (showSaveFilePicker)
 *    -> Opens native OS "Save As" dialog so user always chooses the directory/folder
 * 2. Mobile PWA Web Share API (navigator.share)
 *    -> Lets user pick "Save to Files" (iOS) or "Save to..." / Files by Google (Android)
 * 3. Direct HTTPS Server Download fallback with Content-Disposition: attachment
 *    -> Intercepted by Android DownloadManager & iOS WebKit to prompt download location
 * 4. Extended Lifetime Blob Anchor fallback
 */

export interface SaveFileOptions {
  blob?: Blob;
  filename: string;
  mimeType?: string;
  title?: string;
  directUrl?: string;
  fetchBlob?: () => Promise<Blob>;
}

export interface SaveFileResult {
  success: boolean;
  method: 'picker' | 'share' | 'download' | 'direct_url' | 'cancelled';
  error?: any;
}

export async function saveFileWithPickerOrFallback(options: SaveFileOptions): Promise<SaveFileResult> {
  const {
    blob: initialBlob,
    filename,
    mimeType = 'application/octet-stream',
    title = filename,
    directUrl,
    fetchBlob,
  } = options;

  const ext = filename.split('.').pop() || '';

  // 1. Desktop File System Access API: Always prompt native OS "Save As" dialog
  if (typeof window !== 'undefined' && 'showSaveFilePicker' in window) {
    try {
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

      // Prompt OS native Save As dialog immediately within user gesture
      const handle = await (window as any).showSaveFilePicker(pickerOptions);

      // If user selected location, acquire blob (from prop or fetch callback)
      let dataBlob = initialBlob;
      if (!dataBlob && fetchBlob) {
        dataBlob = await fetchBlob();
      }

      if (dataBlob) {
        const writable = await handle.createWritable();
        await writable.write(dataBlob);
        await writable.close();
        return { success: true, method: 'picker' };
      }
    } catch (err: any) {
      if (err.name === 'AbortError') {
        // User intentionally closed or cancelled the OS Save As dialog
        return { success: false, method: 'cancelled' };
      }
      console.warn('[FileDownloader] showSaveFilePicker failed or unpermitted, falling back:', err);
    }
  }

  // Ensure we have a Blob if we need to share or download locally
  let dataBlob = initialBlob;
  if (!dataBlob && fetchBlob) {
    try {
      dataBlob = await fetchBlob();
    } catch (fetchErr) {
      console.error('[FileDownloader] Failed to fetch file blob:', fetchErr);
    }
  }

  const isMobile = typeof window !== 'undefined' && /android|iphone|ipad|ipod/i.test(navigator.userAgent);

  // 2. Mobile PWA Web Share API: Let user save to Files, Downloads, or Drive
  if (isMobile && dataBlob && typeof navigator !== 'undefined' && 'canShare' in navigator && 'share' in navigator) {
    try {
      const file = new File([dataBlob], filename, { type: mimeType });
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
      console.warn('[FileDownloader] Web Share API failed, proceeding to direct download:', err);
    }
  }

  // 3. Direct HTTPS Server Download URL: Intercepted by OS Download Manager (Android/iOS PWA)
  if (directUrl && typeof window !== 'undefined') {
    try {
      const anchor = document.createElement('a');
      anchor.href = directUrl;
      anchor.setAttribute('download', filename);
      anchor.style.display = 'none';
      anchor.target = '_blank';
      anchor.rel = 'noopener noreferrer';
      document.body.appendChild(anchor);
      anchor.click();

      setTimeout(() => {
        if (anchor.parentNode) {
          anchor.parentNode.removeChild(anchor);
        }
      }, 5000);

      return { success: true, method: 'direct_url' };
    } catch (err) {
      console.warn('[FileDownloader] Direct URL navigation failed, trying blob anchor:', err);
    }
  }

  // 4. Robust Blob URL Fallback with Extended Lifetime (120s)
  if (dataBlob && typeof window !== 'undefined') {
    try {
      const objectUrl = window.URL.createObjectURL(dataBlob);
      const anchor = document.createElement('a');
      anchor.href = objectUrl;
      anchor.download = filename;
      anchor.style.display = 'none';
      anchor.target = '_blank';
      anchor.rel = 'noopener noreferrer';
      document.body.appendChild(anchor);
      anchor.click();

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
      console.error('[FileDownloader] Blob anchor download failed:', err);
      return { success: false, method: 'download', error: err };
    }
  }

  return { success: false, method: 'download' };
}
