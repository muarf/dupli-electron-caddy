// DupliPrintProcessor.h
// Custom Print Processor for Dupli to detect color content in EMF data

#pragma once

#define WIN32_LEAN_AND_MEAN
#include <windows.h>
#include <wingdi.h>
#include <winspool.h>

// Export macros
#ifdef DUPLIPRINTPROCESSOR_EXPORTS
#define PRINTPROC_API __declspec(dllexport)
#else
#define PRINTPROC_API __declspec(dllimport)
#endif

// Define PRINTPROCESSOROPENDATA if not available from winsplp.h
#ifndef _PRINTPROCESSOROPENDATA
typedef struct _PRINTPROCESSOROPENDATA {
  PDEVMODEW pDevMode;
  LPWSTR pDatatype;
  LPWSTR pParameters;
  LPWSTR pDocumentName;
  DWORD JobId;
  LPWSTR pOutputFile;
  LPWSTR pPrinterName;
} PRINTPROCESSOROPENDATA, *PPRINTPROCESSOROPENDATA;
#endif

// Required Print Processor exports (Renamed to avoid conflict with winspool.h,
// mapped in .def)
#ifdef __cplusplus
extern "C" {
#endif

// Enumerate supported data types
PRINTPROC_API BOOL WINAPI DupliEnumPrintProcessorDatatypesW(
    LPWSTR pName, LPWSTR pPrintProcessor, DWORD Level, LPBYTE pDatatypes,
    DWORD cbBuf, LPDWORD pcbNeeded, LPDWORD pcReturned);

// Open a print processor for a specific job
PRINTPROC_API HANDLE WINAPI DupliOpenPrintProcessor(
    LPWSTR pPrinterName, PPRINTPROCESSOROPENDATA pPrintProcessorOpenData);

// Print the document - main processing function
PRINTPROC_API BOOL WINAPI DupliPrintDocumentOnPrintProcessor(
    HANDLE hPrintProcessor, LPWSTR pDocumentName);

// Close the print processor
PRINTPROC_API BOOL WINAPI DupliClosePrintProcessor(HANDLE hPrintProcessor);

// Control the print processor (pause, resume, cancel)
PRINTPROC_API BOOL WINAPI DupliControlPrintProcessor(HANDLE hPrintProcessor,
                                                     DWORD Command);

// Get capabilities
PRINTPROC_API DWORD WINAPI DupliGetPrintProcessorCapabilities(
    LPWSTR pValueName, DWORD dwAttributes, LPBYTE pData, DWORD nSize,
    LPDWORD pcbNeeded);

#ifdef __cplusplus
}
#endif

// Internal structures
typedef struct _DUPLI_PROCESSOR_DATA {
  HANDLE hPrinter;
  LPWSTR pPrinterName;
  LPWSTR pDatatype;
  DWORD JobId;
  BOOL bColorDetected;
  LPVOID pChainData;
} DUPLI_PROCESSOR_DATA, *PDUPLI_PROCESSOR_DATA;
