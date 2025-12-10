// DupliPrintProcessor.cpp
// Custom Print Processor that analyzes EMF content for color detection
// before passing to the original print processor (RC40DPP.dll for RISO or
// winprint.dll)

#define DUPLIPRINTPROCESSOR_EXPORTS
#define WIN32_LEAN_AND_MEAN
#include <stdarg.h>
#include <stdio.h>
#include <stdlib.h>
#include <string.h>
#include <windows.h>
#include <wingdi.h>
#include <winspool.h>

#include "DupliPrintProcessor.h"

// Global state
static HMODULE g_hModule = NULL;
static CRITICAL_SECTION g_CriticalSection;

// Supported data types - we only handle NT EMF
static DATATYPES_INFO_1W g_Datatypes[] = {{(LPWSTR)L"NT EMF 1.003"},
                                          {(LPWSTR)L"NT EMF 1.006"},
                                          {(LPWSTR)L"NT EMF 1.007"},
                                          {(LPWSTR)L"NT EMF 1.008"}};
static const DWORD g_cDatatypes = sizeof(g_Datatypes) / sizeof(g_Datatypes[0]);

// Path for inter-process communication
static const wchar_t *COLOR_RESULT_DIR =
    L"C:\\ProgramData\\Dupli\\PrintProcessor";

// Typedefs for chained functions
typedef HANDLE(WINAPI *PFN_OpenPrintProcessor)(LPWSTR, PPRINTPROCESSOROPENDATA);
typedef BOOL(WINAPI *PFN_PrintDocOnPrintProcessor)(HANDLE, LPWSTR);
typedef BOOL(WINAPI *PFN_ClosePrintProcessor)(HANDLE);
typedef BOOL(WINAPI *PFN_ControlPrintProcessor)(HANDLE, DWORD);

// Internal structure to hold chained processor data
typedef struct _CHAINED_PROCESSOR {
  HMODULE hModule;
  HANDLE hProcessor;
  PFN_ClosePrintProcessor pfnClose;
  PFN_PrintDocOnPrintProcessor pfnPrint;
  PFN_ControlPrintProcessor pfnControl;
} CHAINED_PROCESSOR, *PCHAINED_PROCESSOR;

// Helper for debug logging
static void WriteDebugLog(const char *format, ...) {
  FILE *f = NULL;
  fopen_s(&f, "C:\\ProgramData\\Dupli\\PrintProcessor\\debug.log", "a+");
  if (f) {
    va_list args;
    va_start(args, format);
    vfprintf(f, format, args);
    fprintf(f, "\n");
    va_end(args);
    fclose(f);
  }
}

// DLL entry point
BOOL WINAPI DllMain(HINSTANCE hinstDLL, DWORD dwReason, LPVOID lpReserved) {
  switch (dwReason) {
  case DLL_PROCESS_ATTACH:
    g_hModule = hinstDLL;
    InitializeCriticalSection(&g_CriticalSection);
    DisableThreadLibraryCalls(hinstDLL);
    WriteDebugLog("DLL_PROCESS_ATTACH");
    break;
  case DLL_PROCESS_DETACH:
    DeleteCriticalSection(&g_CriticalSection);
    WriteDebugLog("DLL_PROCESS_DETACH");
    break;
  }
  return TRUE;
}

// Enumerate supported data types
BOOL WINAPI DupliEnumPrintProcessorDatatypesW(LPWSTR pName,
                                              LPWSTR pPrintProcessor,
                                              DWORD Level, LPBYTE pDatatypes,
                                              DWORD cbBuf, LPDWORD pcbNeeded,
                                              LPDWORD pcReturned) {
  if (Level != 1) {
    SetLastError(ERROR_INVALID_LEVEL);
    return FALSE;
  }

  *pcbNeeded = g_cDatatypes * sizeof(DATATYPES_INFO_1W);
  *pcReturned = 0;

  if (cbBuf < *pcbNeeded) {
    SetLastError(ERROR_INSUFFICIENT_BUFFER);
    return FALSE;
  }

  if (!pDatatypes) {
    SetLastError(ERROR_INVALID_PARAMETER);
    return FALSE;
  }

  memcpy(pDatatypes, g_Datatypes, *pcbNeeded);
  *pcReturned = g_cDatatypes;

  return TRUE;
}

// Helper to duplicate a wide string
static LPWSTR DuplicateString(LPCWSTR src) {
  if (!src)
    return NULL;
  size_t len = wcslen(src) + 1;
  LPWSTR dst = (LPWSTR)LocalAlloc(LMEM_FIXED, len * sizeof(wchar_t));
  if (dst) {
    memcpy(dst, src, len * sizeof(wchar_t));
  }
  return dst;
}

// Constants for Chain Loading
static const wchar_t *CHAIN_DLL_RISO = L"\\spool\\prtprocs\\x64\\RC40DPP.dll";
static const wchar_t *CHAIN_DLL_STD = L"\\spool\\prtprocs\\x64\\winprint.dll";

// Helper to load the next processor in chain
static BOOL LoadChainProcessor(LPWSTR pPrinterName,
                               PPRINTPROCESSOROPENDATA pOpenData,
                               PDUPLI_PROCESSOR_DATA pData) {
  wchar_t systemDir[MAX_PATH];
  GetSystemDirectoryW(systemDir, MAX_PATH);

  wchar_t dllPath[MAX_PATH];
  HMODULE hChainInfo = NULL;

  // 1. Try RISO Processor first
  wcscpy_s(dllPath, systemDir);
  wcscat_s(dllPath, CHAIN_DLL_RISO);
  hChainInfo = LoadLibraryW(dllPath);

  // 2. Fallback to Standard WinPrint
  if (!hChainInfo) {
    wcscpy_s(dllPath, systemDir);
    wcscat_s(dllPath, CHAIN_DLL_STD);
    hChainInfo = LoadLibraryW(dllPath);
  }

  if (!hChainInfo) {
    WriteDebugLog("Failed to load any chained processor");
    return FALSE;
  }

  PFN_OpenPrintProcessor pfnOpen =
      (PFN_OpenPrintProcessor)GetProcAddress(hChainInfo, "OpenPrintProcessor");
  PFN_PrintDocOnPrintProcessor pfnPrint =
      (PFN_PrintDocOnPrintProcessor)GetProcAddress(
          hChainInfo, "PrintDocumentOnPrintProcessor");
  PFN_ClosePrintProcessor pfnClose = (PFN_ClosePrintProcessor)GetProcAddress(
      hChainInfo, "ClosePrintProcessor");
  PFN_ControlPrintProcessor pfnControl =
      (PFN_ControlPrintProcessor)GetProcAddress(hChainInfo,
                                                "ControlPrintProcessor");

  if (pfnOpen && pfnPrint && pfnClose) {
    HANDLE hChainProc = pfnOpen(pPrinterName, pOpenData);
    if (hChainProc) {
      PCHAINED_PROCESSOR pChain = (PCHAINED_PROCESSOR)LocalAlloc(
          LMEM_FIXED | LMEM_ZEROINIT, sizeof(CHAINED_PROCESSOR));
      if (pChain) {
        pChain->hModule = hChainInfo;
        pChain->hProcessor = hChainProc;
        pChain->pfnClose = pfnClose;
        pChain->pfnPrint = pfnPrint;
        pChain->pfnControl = pfnControl;
        pData->pChainData = pChain;
        return TRUE;
      }
      pfnClose(hChainProc);
    }
  }

  FreeLibrary(hChainInfo);
  return FALSE;
}

// Open print processor for a job
HANDLE WINAPI DupliOpenPrintProcessor(LPWSTR pPrinterName,
                                      PPRINTPROCESSOROPENDATA pOpenData) {
  if (!pPrinterName || !pOpenData) {
    SetLastError(ERROR_INVALID_PARAMETER);
    return NULL;
  }

  WriteDebugLog("OpenPrintProcessor JobId=%d", pOpenData->JobId);

  // Allocate our processor data
  PDUPLI_PROCESSOR_DATA pData = (PDUPLI_PROCESSOR_DATA)LocalAlloc(
      LMEM_FIXED | LMEM_ZEROINIT, sizeof(DUPLI_PROCESSOR_DATA));
  if (!pData) {
    SetLastError(ERROR_NOT_ENOUGH_MEMORY);
    return NULL;
  }

  pData->JobId = pOpenData->JobId;
  pData->pPrinterName = DuplicateString(pPrinterName);
  pData->pDatatype = DuplicateString(pOpenData->pDatatype);

  // Open handle for EMF analysis (used in PrintDocument)
  OpenPrinterW(pPrinterName, &pData->hPrinter, NULL);

  // Initialize Chain
  if (!LoadChainProcessor(pPrinterName, pOpenData, pData)) {
    // If chain fails, we can't really print, but we should clean up
    if (pData->hPrinter)
      ClosePrinter(pData->hPrinter);
    LocalFree(pData->pPrinterName);
    LocalFree(pData->pDatatype);
    LocalFree(pData);
    return NULL;
  }

  return (HANDLE)pData;
}

// EMF enumeration callback for color detection
struct EMF_ENUM_DATA {
  BOOL bColorFound;
  int nRecordsChecked;
};

int CALLBACK EmfEnumProc(HDC hdc, HANDLETABLE *lpHandleTable,
                         const ENHMETARECORD *lpEMFR, int nHandles,
                         LPARAM lParam) {
  EMF_ENUM_DATA *pEnumData = (EMF_ENUM_DATA *)lParam;
  pEnumData->nRecordsChecked++;

  switch (lpEMFR->iType) {
  case EMR_CREATEBRUSHINDIRECT: {
    const EMRCREATEBRUSHINDIRECT *pBrush =
        (const EMRCREATEBRUSHINDIRECT *)lpEMFR;
    COLORREF color = pBrush->lb.lbColor;
    if (abs((int)GetRValue(color) - (int)GetGValue(color)) > 5 ||
        abs((int)GetGValue(color) - (int)GetBValue(color)) > 5) {
      pEnumData->bColorFound = TRUE;
      return 0;
    }
    break;
  }
  case EMR_CREATEPEN: {
    const EMRCREATEPEN *pPen = (const EMRCREATEPEN *)lpEMFR;
    COLORREF color = pPen->lopn.lopnColor;
    if (abs((int)GetRValue(color) - (int)GetGValue(color)) > 5 ||
        abs((int)GetGValue(color) - (int)GetBValue(color)) > 5) {
      pEnumData->bColorFound = TRUE;
      return 0;
    }
    break;
  }
  case EMR_EXTCREATEPEN: {
    const EMREXTCREATEPEN *pPen = (const EMREXTCREATEPEN *)lpEMFR;
    COLORREF color = pPen->elp.elpColor;
    if (abs((int)GetRValue(color) - (int)GetGValue(color)) > 5 ||
        abs((int)GetGValue(color) - (int)GetBValue(color)) > 5) {
      pEnumData->bColorFound = TRUE;
      return 0;
    }
    break;
  }
  case EMR_SETTEXTCOLOR:
  case EMR_SETBKCOLOR: {
    const COLORREF *pColor =
        (const COLORREF *)((const BYTE *)lpEMFR + sizeof(EMR));
    if (abs((int)GetRValue(*pColor) - (int)GetGValue(*pColor)) > 5 ||
        abs((int)GetGValue(*pColor) - (int)GetBValue(*pColor)) > 5) {
      pEnumData->bColorFound = TRUE;
      return 0;
    }
    break;
  }
  case EMR_SETPIXELV: {
    const EMRSETPIXELV *pPixel = (const EMRSETPIXELV *)lpEMFR;
    COLORREF color = pPixel->crColor;
    if (abs((int)GetRValue(color) - (int)GetGValue(color)) > 5 ||
        abs((int)GetGValue(color) - (int)GetBValue(color)) > 5) {
      pEnumData->bColorFound = TRUE;
      return 0;
    }
    break;
  }
  }
  return 1;
}

static void WriteColorResult(DWORD jobId, BOOL isColor) {
  CreateDirectoryW(L"C:\\ProgramData\\Dupli", NULL);
  CreateDirectoryW(COLOR_RESULT_DIR, NULL);
  wchar_t filePath[MAX_PATH];
  swprintf_s(filePath, MAX_PATH, L"%s\\%lu.txt", COLOR_RESULT_DIR, jobId);
  HANDLE hFile = CreateFileW(filePath, GENERIC_WRITE, 0, NULL, CREATE_ALWAYS,
                             FILE_ATTRIBUTE_NORMAL, NULL);
  if (hFile != INVALID_HANDLE_VALUE) {
    const char *result = isColor ? "COLOR\n" : "GRAYSCALE\n";
    DWORD written;
    WriteFile(hFile, result, (DWORD)strlen(result), &written, NULL);
    CloseHandle(hFile);
    WriteDebugLog("Wrote result for Job %d: %s", jobId,
                  isColor ? "COLOR" : "GRAYSCALE");
  } else {
    WriteDebugLog("Failed to write result for Job %d (Error %d)", jobId,
                  GetLastError());
  }
}

// Main print function
BOOL WINAPI DupliPrintDocumentOnPrintProcessor(HANDLE hPrintProcessor,
                                               LPWSTR pDocumentName) {
  PDUPLI_PROCESSOR_DATA pData = (PDUPLI_PROCESSOR_DATA)hPrintProcessor;
  if (!pData)
    return FALSE;

  WriteDebugLog("PrintDocumentOnPrintProcessor JobId=%d", pData->JobId);

  BOOL bColorDetected = FALSE;

  // Analyze EMF
  HMODULE hGdi = GetModuleHandleW(L"gdi32.dll");
  if (hGdi) {
    typedef HANDLE(WINAPI * PFN_GdiGetSpoolFileHandle)(LPWSTR, LPDEVMODEW,
                                                       LPWSTR);
    typedef BOOL(WINAPI * PFN_GdiDeleteSpoolFileHandle)(HANDLE);
    typedef HANDLE(WINAPI * PFN_GdiGetPageHandle)(HANDLE, DWORD, LPDWORD);

    PFN_GdiGetSpoolFileHandle pfnGetSpoolFile =
        (PFN_GdiGetSpoolFileHandle)GetProcAddress(hGdi,
                                                  "GdiGetSpoolFileHandle");
    PFN_GdiDeleteSpoolFileHandle pfnDeleteSpoolFile =
        (PFN_GdiDeleteSpoolFileHandle)GetProcAddress(
            hGdi, "GdiDeleteSpoolFileHandle");
    PFN_GdiGetPageHandle pfnGetPageHandle =
        (PFN_GdiGetPageHandle)GetProcAddress(hGdi, "GdiGetPageHandle");

    if (pfnGetSpoolFile) {
      HANDLE hSpoolFile =
          pfnGetSpoolFile(pData->pPrinterName, NULL, pData->pDatatype);
      if (hSpoolFile) {
        if (pfnGetPageHandle) {
          DWORD dwPageType = 0;
          HANDLE hPageEMF = pfnGetPageHandle(hSpoolFile, 1, &dwPageType);
          if (hPageEMF) {
            EMF_ENUM_DATA enumData = {FALSE, 0};
            EnumEnhMetaFile(NULL, (HENHMETAFILE)hPageEMF, EmfEnumProc,
                            &enumData, NULL);
            bColorDetected = enumData.bColorFound;
          } else {
            WriteDebugLog("GdiGetPageHandle failed");
          }
        } else {
          WriteDebugLog("GdiGetPageHandle not found");
        }
        if (pfnDeleteSpoolFile)
          pfnDeleteSpoolFile(hSpoolFile);
      } else {
        WriteDebugLog("GdiGetSpoolFileHandle return NULL");
      }
    }
  }

  WriteColorResult(pData->JobId, bColorDetected);
  pData->bColorDetected = bColorDetected;

  // Chain to next processor
  PCHAINED_PROCESSOR pChain = (PCHAINED_PROCESSOR)pData->pChainData;
  if (pChain && pChain->pfnPrint) {
    return pChain->pfnPrint(pChain->hProcessor, pDocumentName);
  }

  return FALSE;
}

BOOL WINAPI DupliClosePrintProcessor(HANDLE hPrintProcessor) {
  PDUPLI_PROCESSOR_DATA pData = (PDUPLI_PROCESSOR_DATA)hPrintProcessor;
  if (!pData)
    return FALSE;

  // Close chained processor
  PCHAINED_PROCESSOR pChain = (PCHAINED_PROCESSOR)pData->pChainData;
  if (pChain) {
    if (pChain->pfnClose)
      pChain->pfnClose(pChain->hProcessor);
    if (pChain->hModule)
      FreeLibrary(pChain->hModule);
    LocalFree(pChain);
  }

  if (pData->hPrinter)
    ClosePrinter(pData->hPrinter);
  if (pData->pPrinterName)
    LocalFree(pData->pPrinterName);
  if (pData->pDatatype)
    LocalFree(pData->pDatatype);
  LocalFree(pData);
  return TRUE;
}

BOOL WINAPI DupliControlPrintProcessor(HANDLE hPrintProcessor, DWORD Command) {
  PDUPLI_PROCESSOR_DATA pData = (PDUPLI_PROCESSOR_DATA)hPrintProcessor;
  if (!pData)
    return FALSE;

  PCHAINED_PROCESSOR pChain = (PCHAINED_PROCESSOR)pData->pChainData;
  if (pChain && pChain->pfnControl) {
    return pChain->pfnControl(pChain->hProcessor, Command);
  }
  return TRUE;
}

DWORD WINAPI DupliGetPrintProcessorCapabilities(LPWSTR pValueName,
                                                DWORD dwAttributes,
                                                LPBYTE pData, DWORD nSize,
                                                LPDWORD pcbNeeded) {
  // No easy way to chain this without opening the processor, but usually this
  // is just a registry lookup or simple return.
  *pcbNeeded = 0;
  return 0;
}
