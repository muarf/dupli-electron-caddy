#include <napi.h>
#include <windows.h>
#include <winspool.h>
#include <vector>
#include <string>

// Constantes pour DeviceCapabilities
#define DC_BINS 6
#define DC_BINNAMES 12
#define DC_PAPERS 2
#define DC_PAPERSIZE 3
#define DC_COLORDEVICE 26
#define DC_DUPLEX 7

// Structure pour stocker les informations d'une imprimante
struct PrinterInfo {
    std::string name;
    std::string displayName;
    bool isDefault;
    DWORD status;
};

// Convertir std::string en Napi::String
Napi::String StringToNapiString(Napi::Env env, const std::string& str) {
    return Napi::String::New(env, str.c_str());
}

// Convertir LPSTR en std::string
std::string LPSTRToString(LPSTR lpstr) {
    if (lpstr == nullptr) return "";
    return std::string(lpstr);
}

// Obtenir la liste des imprimantes
Napi::Array GetPrinters(const Napi::CallbackInfo& info) {
    Napi::Env env = info.Env();
    
    DWORD needed, returned;
    EnumPrinters(PRINTER_ENUM_LOCAL | PRINTER_ENUM_CONNECTIONS, NULL, 2, NULL, 0, &needed, &returned);
    
    if (needed == 0) {
        return Napi::Array::New(env, 0);
    }
    
    std::vector<BYTE> buffer(needed);
    PRINTER_INFO_2* printers = (PRINTER_INFO_2*)buffer.data();
    
    if (!EnumPrinters(PRINTER_ENUM_LOCAL | PRINTER_ENUM_CONNECTIONS, NULL, 2, 
                      buffer.data(), needed, &needed, &returned)) {
        Napi::Error::New(env, "Erreur lors de l'énumération des imprimantes").ThrowAsJavaScriptException();
        return Napi::Array::New(env, 0);
    }
    
    // Obtenir l'imprimante par défaut
    DWORD defaultSize = 0;
    GetDefaultPrinter(NULL, &defaultSize);
    std::string defaultPrinterName;
    if (defaultSize > 0) {
        std::vector<CHAR> defaultBuffer(defaultSize);
        GetDefaultPrinter(defaultBuffer.data(), &defaultSize);
        defaultPrinterName = std::string(defaultBuffer.data());
    }
    
    Napi::Array result = Napi::Array::New(env, returned);
    
    for (DWORD i = 0; i < returned; i++) {
        Napi::Object printer = Napi::Object::New(env);
        std::string name = LPSTRToString(printers[i].pPrinterName);
        
        printer.Set("name", StringToNapiString(env, name));
        printer.Set("displayName", StringToNapiString(env, printers[i].pComment ? LPSTRToString(printers[i].pComment) : name));
        printer.Set("isDefault", Napi::Boolean::New(env, name == defaultPrinterName));
        printer.Set("status", Napi::Number::New(env, printers[i].Status));
        
        result.Set(i, printer);
    }
    
    return result;
}

// Obtenir les capacités d'une imprimante
Napi::Object GetPrinterCapabilities(const Napi::CallbackInfo& info) {
    Napi::Env env = info.Env();
    
    if (info.Length() < 1 || !info[0].IsString()) {
        Napi::TypeError::New(env, "Le nom de l'imprimante est requis").ThrowAsJavaScriptException();
        return Napi::Object::New(env);
    }
    
    std::string printerName = info[0].As<Napi::String>().Utf8Value();
    
    // Ouvrir l'imprimante
    HANDLE hPrinter;
    PRINTER_DEFAULTS defaults = {0};
    defaults.DesiredAccess = PRINTER_ACCESS_USE;
    
    if (!OpenPrinter((LPSTR)printerName.c_str(), &hPrinter, &defaults)) {
        Napi::Error::New(env, "Impossible d'ouvrir l'imprimante").ThrowAsJavaScriptException();
        return Napi::Object::New(env);
    }
    
    Napi::Object capabilities = Napi::Object::New(env);
    
    // Obtenir les bacs (InputSlots)
    DWORD binCount = DeviceCapabilities(printerName.c_str(), NULL, DC_BINS, NULL, NULL);
    if (binCount > 0) {
        std::vector<WORD> bins(binCount);
        std::vector<CHAR> binNames(binCount * 24); // 24 caractères par nom
        
        DeviceCapabilities(printerName.c_str(), NULL, DC_BINS, (LPSTR)bins.data(), NULL);
        DeviceCapabilities(printerName.c_str(), NULL, DC_BINNAMES, binNames.data(), NULL);
        
        Napi::Array inputSlots = Napi::Array::New(env, binCount);
        for (DWORD i = 0; i < binCount; i++) {
            Napi::Object slot = Napi::Object::New(env);
            std::string binName = std::string((char*)&binNames[i * 24], 24);
            // Supprimer les caractères nuls
            binName = binName.substr(0, binName.find('\0'));
            
            slot.Set("name", StringToNapiString(env, binName));
            slot.Set("value", StringToNapiString(env, binName));
            inputSlots.Set(i, slot);
        }
        capabilities.Set("inputSlots", inputSlots);
    } else {
        capabilities.Set("inputSlots", Napi::Array::New(env, 0));
    }
    
    // Obtenir les formats papier
    DWORD paperCount = DeviceCapabilities(printerName.c_str(), NULL, DC_PAPERS, NULL, NULL);
    if (paperCount > 0) {
        std::vector<WORD> papers(paperCount);
        std::vector<POINT> paperSizes(paperCount);
        
        DeviceCapabilities(printerName.c_str(), NULL, DC_PAPERS, (LPSTR)papers.data(), NULL);
        DeviceCapabilities(printerName.c_str(), NULL, DC_PAPERSIZE, (LPSTR)paperSizes.data(), NULL);
        
        // Mapping des constantes Windows vers les noms de formats
        const char* paperNames[] = {
            "Letter", "Legal", "A3", "A4", "A5", "B4", "B5", "Folio", "Executive", "Ledger"
        };
        
        Napi::Array pageSizes = Napi::Array::New(env);
        DWORD validCount = 0;
        for (DWORD i = 0; i < paperCount; i++) {
            if (papers[i] < sizeof(paperNames) / sizeof(paperNames[0])) {
                Napi::Object size = Napi::Object::New(env);
                std::string name = paperNames[papers[i]];
                
                size.Set("name", StringToNapiString(env, name));
                size.Set("value", StringToNapiString(env, name));
                // Convertir de 1/100 de mm en mm
                size.Set("width", Napi::Number::New(env, paperSizes[i].x / 100.0));
                size.Set("height", Napi::Number::New(env, paperSizes[i].y / 100.0));
                
                pageSizes.Set(validCount++, size);
            }
        }
        capabilities.Set("pageSizes", pageSizes);
    } else {
        capabilities.Set("pageSizes", Napi::Array::New(env, 0));
    }
    
    // Vérifier le support duplex
    DWORD duplex = DeviceCapabilities(printerName.c_str(), NULL, DC_DUPLEX, NULL, NULL);
    capabilities.Set("duplex", Napi::Boolean::New(env, duplex == 1));
    
    // Vérifier le support couleur
    DWORD color = DeviceCapabilities(printerName.c_str(), NULL, DC_COLORDEVICE, NULL, NULL);
    capabilities.Set("color", Napi::Boolean::New(env, color == 1));
    
    // Modes couleur par défaut
    Napi::Array colorModes = Napi::Array::New(env);
    if (color == 1) {
        colorModes.Set(0, StringToNapiString(env, "Color"));
        colorModes.Set(1, StringToNapiString(env, "Monochrome"));
    } else {
        colorModes.Set(0, StringToNapiString(env, "Monochrome"));
    }
    capabilities.Set("colorModes", colorModes);
    
    // Résolutions (par défaut, on ne les récupère pas facilement via DeviceCapabilities)
    capabilities.Set("resolutions", Napi::Array::New(env, 0));
    
    ClosePrinter(hPrinter);
    
    return capabilities;
}

// Lancer un job d'impression
Napi::Object PrintJob(const Napi::CallbackInfo& info) {
    Napi::Env env = info.Env();
    
    if (info.Length() < 2 || !info[0].IsString() || !info[1].IsObject()) {
        Napi::TypeError::New(env, "Le chemin du PDF et les options sont requis").ThrowAsJavaScriptException();
        return Napi::Object::New(env);
    }
    
    std::string pdfPath = info[0].As<Napi::String>().Utf8Value();
    Napi::Object options = info[1].As<Napi::Object>();
    
    std::string printerName = options.Get("printer").As<Napi::String>().Utf8Value();
    
    // Log des options reçues
    fprintf(stderr, "[PRINT_ENGINE_C++] PrintJob appelé - PDF: %s, Imprimante: %s\n", pdfPath.c_str(), printerName.c_str());
    if (options.Has("pageSize")) {
        fprintf(stderr, "[PRINT_ENGINE_C++] Option pageSize: %s\n", options.Get("pageSize").As<Napi::String>().Utf8Value().c_str());
    }
    if (options.Has("duplex")) {
        fprintf(stderr, "[PRINT_ENGINE_C++] Option duplex: %s\n", options.Get("duplex").As<Napi::String>().Utf8Value().c_str());
    }
    if (options.Has("colorMode")) {
        fprintf(stderr, "[PRINT_ENGINE_C++] Option colorMode: %s\n", options.Get("colorMode").As<Napi::String>().Utf8Value().c_str());
    }
    
    // Ouvrir l'imprimante
    HANDLE hPrinter;
    PRINTER_DEFAULTS defaults = {0};
    defaults.DesiredAccess = PRINTER_ACCESS_USE;
    
    if (!OpenPrinter((LPSTR)printerName.c_str(), &hPrinter, &defaults)) {
        Napi::Error::New(env, "Impossible d'ouvrir l'imprimante").ThrowAsJavaScriptException();
        return Napi::Object::New(env);
    }
    
    // Obtenir le DEVMODE
    DWORD devModeSize = DocumentProperties(NULL, hPrinter, (LPSTR)printerName.c_str(), NULL, NULL, 0);
    if (devModeSize == 0) {
        ClosePrinter(hPrinter);
        Napi::Error::New(env, "Impossible d'obtenir le DEVMODE").ThrowAsJavaScriptException();
        return Napi::Object::New(env);
    }
    
    std::vector<BYTE> devModeBuffer(devModeSize);
    PDEVMODE devMode = (PDEVMODE)devModeBuffer.data();
    
    if (DocumentProperties(NULL, hPrinter, (LPSTR)printerName.c_str(), devMode, NULL, DM_OUT_BUFFER) < 0) {
        ClosePrinter(hPrinter);
        Napi::Error::New(env, "Impossible de récupérer le DEVMODE").ThrowAsJavaScriptException();
        return Napi::Object::New(env);
    }
    
    // Log du DEVMODE initial
    fprintf(stderr, "[PRINT_ENGINE_C++] DEVMODE initial - Size: %d, Fields: 0x%08X\n", devMode->dmSize, devMode->dmFields);
    if (devMode->dmFields & DM_PAPERSIZE) {
        fprintf(stderr, "[PRINT_ENGINE_C++]   - dmPaperSize: %d\n", devMode->dmPaperSize);
    }
    if (devMode->dmFields & DM_DUPLEX) {
        fprintf(stderr, "[PRINT_ENGINE_C++]   - dmDuplex: %d\n", devMode->dmDuplex);
    }
    if (devMode->dmFields & DM_COLOR) {
        fprintf(stderr, "[PRINT_ENGINE_C++]   - dmColor: %d\n", devMode->dmColor);
    }
    
    // Modifier le DEVMODE selon les options
    if (options.Has("copies")) {
        devMode->dmCopies = options.Get("copies").As<Napi::Number>().Uint32Value();
        devMode->dmFields |= DM_COPIES;
    }
    
    if (options.Has("duplex")) {
        std::string duplex = options.Get("duplex").As<Napi::String>().Utf8Value();
        if (duplex == "DuplexNoTumble") {
            devMode->dmDuplex = DMDUP_HORIZONTAL;
        } else if (duplex == "DuplexTumble") {
            devMode->dmDuplex = DMDUP_VERTICAL;
        } else {
            devMode->dmDuplex = DMDUP_SIMPLEX;
        }
        devMode->dmFields |= DM_DUPLEX;
    }
    
    if (options.Has("colorMode")) {
        std::string colorMode = options.Get("colorMode").As<Napi::String>().Utf8Value();
        if (colorMode == "Monochrome") {
            devMode->dmColor = DMCOLOR_MONOCHROME;
        } else {
            devMode->dmColor = DMCOLOR_COLOR;
        }
        devMode->dmFields |= DM_COLOR;
    }
    
    // Gérer le format papier (pageSize)
    if (options.Has("pageSize")) {
        std::string pageSize = options.Get("pageSize").As<Napi::String>().Utf8Value();
        // Mapper les noms de formats vers les constantes Windows
        if (pageSize == "A4" || pageSize == "iso-a4") {
            devMode->dmPaperSize = DMPAPER_A4;
        } else if (pageSize == "A3" || pageSize == "iso-a3") {
            devMode->dmPaperSize = DMPAPER_A3;
        } else if (pageSize == "Letter" || pageSize == "na-letter") {
            devMode->dmPaperSize = DMPAPER_LETTER;
        } else if (pageSize == "Legal" || pageSize == "na-legal") {
            devMode->dmPaperSize = DMPAPER_LEGAL;
        } else if (pageSize == "A5" || pageSize == "iso-a5") {
            devMode->dmPaperSize = DMPAPER_A5;
        } else if (pageSize == "B4" || pageSize == "iso-b4") {
            devMode->dmPaperSize = DMPAPER_B4;
        } else if (pageSize == "B5" || pageSize == "iso-b5") {
            devMode->dmPaperSize = DMPAPER_B5;
        }
        // Si un format a été défini, activer le flag
        if (pageSize != "Default" && pageSize != "") {
            devMode->dmFields |= DM_PAPERSIZE;
        }
    }
    
    // Gérer la résolution si disponible
    if (options.Has("resolution")) {
        std::string resolution = options.Get("resolution").As<Napi::String>().Utf8Value();
        // Extraire la valeur DPI (ex: "300dpi" -> 300)
        if (resolution != "Default" && resolution != "") {
            int dpi = 0;
            if (sscanf(resolution.c_str(), "%ddpi", &dpi) == 1 || sscanf(resolution.c_str(), "%d", &dpi) == 1) {
                devMode->dmPrintQuality = dpi;
                devMode->dmYResolution = dpi;
                devMode->dmFields |= DM_PRINTQUALITY;
                devMode->dmFields |= DM_YRESOLUTION;
            }
        }
    }
    
    // Log du DEVMODE modifié avant application
    fprintf(stderr, "[PRINT_ENGINE_C++] DEVMODE modifié - Fields: 0x%08X\n", devMode->dmFields);
    if (devMode->dmFields & DM_PAPERSIZE) {
        fprintf(stderr, "[PRINT_ENGINE_C++]   - dmPaperSize: %d\n", devMode->dmPaperSize);
    }
    if (devMode->dmFields & DM_DUPLEX) {
        fprintf(stderr, "[PRINT_ENGINE_C++]   - dmDuplex: %d (1=Simplex, 2=Vertical, 3=Horizontal)\n", devMode->dmDuplex);
    }
    if (devMode->dmFields & DM_COLOR) {
        fprintf(stderr, "[PRINT_ENGINE_C++]   - dmColor: %d (1=Monochrome, 2=Color)\n", devMode->dmColor);
    }
    
    // Appliquer le DEVMODE modifié
    if (DocumentProperties(NULL, hPrinter, (LPSTR)printerName.c_str(), devMode, devMode, DM_IN_BUFFER | DM_OUT_BUFFER) < 0) {
        ClosePrinter(hPrinter);
        Napi::Error::New(env, "Impossible de modifier le DEVMODE").ThrowAsJavaScriptException();
        return Napi::Object::New(env);
    }
    
    // Utiliser ShellExecute pour imprimer le PDF (plus simple que GDI)
    std::string command = "print";
    std::string params = "\"" + pdfPath + "\"";
    
    fprintf(stderr, "[PRINT_ENGINE_C++] Lancement de l'impression via ShellExecute...\n");
    HINSTANCE result = ShellExecute(NULL, "print", pdfPath.c_str(), NULL, NULL, SW_HIDE);
    
    ClosePrinter(hPrinter);
    
    fprintf(stderr, "[PRINT_ENGINE_C++] ShellExecute résultat: %p\n", result);
    
    if ((INT_PTR)result <= 32) {
        Napi::Error::New(env, "Erreur lors du lancement de l'impression").ThrowAsJavaScriptException();
        return Napi::Object::New(env);
    }
    
    Napi::Object resultObj = Napi::Object::New(env);
    resultObj.Set("success", Napi::Boolean::New(env, true));
    resultObj.Set("message", StringToNapiString(env, "Impression lancée avec succès"));
    resultObj.Set("printer", StringToNapiString(env, printerName));
    
    return resultObj;
}

// Initialiser le module
Napi::Object Init(Napi::Env env, Napi::Object exports) {
    exports.Set(Napi::String::New(env, "getPrinters"),
                Napi::Function::New(env, GetPrinters));
    exports.Set(Napi::String::New(env, "getPrinterCapabilities"),
                Napi::Function::New(env, GetPrinterCapabilities));
    exports.Set(Napi::String::New(env, "printJob"),
                Napi::Function::New(env, PrintJob));
    return exports;
}

NODE_API_MODULE(win32_printer, Init)

