#include <napi.h>

Napi::Value StartMonitoring(const Napi::CallbackInfo& info) {
    return Napi::Boolean::New(info.Env(), false);
}

Napi::Value StopMonitoring(const Napi::CallbackInfo& info) {
    return info.Env().Undefined();
}

Napi::Value GetPrinters(const Napi::CallbackInfo& info) {
    return Napi::Array::New(info.Env(), 0);
}

Napi::Value GetPrinterCapabilities(const Napi::CallbackInfo& info) {
    return Napi::Object::New(info.Env());
}

Napi::Value PrintJob(const Napi::CallbackInfo& info) {
    return Napi::Boolean::New(info.Env(), false);
}

Napi::Value ReanalyzeJob(const Napi::CallbackInfo& info) {
    return Napi::Object::New(info.Env());
}

Napi::Object Init(Napi::Env env, Napi::Object exports) {
    exports.Set("startPrinterMonitor", Napi::Function::New(env, StartMonitoring));
    exports.Set("stopPrinterMonitor", Napi::Function::New(env, StopMonitoring));
    exports.Set("getPrinters", Napi::Function::New(env, GetPrinters));
    exports.Set("getPrinterCapabilities", Napi::Function::New(env, GetPrinterCapabilities));
    exports.Set("printJob", Napi::Function::New(env, PrintJob));
    exports.Set("reanalyzeJob", Napi::Function::New(env, ReanalyzeJob));
    return exports;
}

NODE_API_MODULE(printer_monitor, Init)
