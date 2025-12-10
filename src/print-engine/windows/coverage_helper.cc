
// Helper to calculate ink/toner coverage by rendering EMF to a small bitmap
float CalculatePageCoverage(HENHMETAFILE hEmf) {
  if (!hEmf)
    return 0.0f;

  // Use a fixed thumbnail size for calculation (e.g. 500x500 approx)
  // Enough resolution for stats, fast to render
  const int THUMB_W = 250;
  const int THUMB_H = 350; // Typical A4 aspect ratio

  HDC hDC = CreateCompatibleDC(NULL);
  if (!hDC)
    return 0.0f;

  // Create 32-bit bitmap for easy pixel access
  BITMAPINFO bmi = {0};
  bmi.bmiHeader.biSize = sizeof(BITMAPINFOHEADER);
  bmi.bmiHeader.biWidth = THUMB_W;
  bmi.bmiHeader.biHeight = -THUMB_H; // Top-down
  bmi.bmiHeader.biPlanes = 1;
  bmi.bmiHeader.biBitCount = 32;
  bmi.bmiHeader.biCompression = BI_RGB;

  void *pBits = NULL;
  HBITMAP hBmp = CreateDIBSection(hDC, &bmi, DIB_RGB_COLORS, &pBits, NULL, 0);
  if (!hBmp || !pBits) {
    DeleteDC(hDC);
    return 0.0f;
  }

  HBITMAP hOldBmp = (HBITMAP)SelectObject(hDC, hBmp);

  // Initial fill: White
  RECT rect = {0, 0, THUMB_W, THUMB_H};
  FillRect(hDC, &rect, (HBRUSH)GetStockObject(WHITE_BRUSH));

  // Play EMF (render it)
  PlayEnhMetaFile(hDC, hEmf, &rect);

  // Count non-white pixels
  // 32-bit: B, G, R, A
  DWORD *pixels = (DWORD *)pBits;
  int totalPixels = THUMB_W * THUMB_H;
  int filledPixels = 0;

  for (int i = 0; i < totalPixels; i++) {
    // Mask out alpha just in case, though usually 0 in GDI RGB
    DWORD color = pixels[i] & 0x00FFFFFF;
    if (color != 0x00FFFFFF) { // Not White
      filledPixels++;
    }
  }

  SelectObject(hDC, hOldBmp);
  DeleteObject(hBmp);
  DeleteDC(hDC);

  return (float)((double)filledPixels / (double)totalPixels * 100.0);
}
