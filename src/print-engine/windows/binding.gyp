{
  "targets": [
    {
      "target_name": "win32-printer",
      "include_dirs": [
        "<!@(node -p \"require('node-addon-api').include\")"
      ],
      "dependencies": [
        "<!(node -p \"require('node-addon-api').gyp\")"
      ],
      "cflags!": [ "-fno-exceptions" ],
      "cflags_cc!": [ "-fno-exceptions" ],
      "defines": [ "NAPI_DISABLE_CPP_EXCEPTIONS" ],
      "msvs_settings": {
        "VCLibrarianTool": {
          "LinkTimeCodeGeneration": "false"
        },
        "VCLinkerTool": {
          "LinkTimeCodeGeneration": "0"
        }
      },
      "conditions": [
        ["OS=='win'", {
          "sources": [ "win32-printer.cc" ],
          "libraries": [
            "-lwinspool.lib"
          ]
        }, {
          "sources": [ "stubs.cc" ]
        }]
      ]
    }
  ]
}
