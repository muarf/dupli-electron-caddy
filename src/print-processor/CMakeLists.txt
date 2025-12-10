cmake_minimum_required(VERSION 3.16)
project(DupliPrintProcessor)

set(CMAKE_CXX_STANDARD 17)
set(CMAKE_CXX_STANDARD_REQUIRED ON)

# Build as DLL
add_library(DupliPrintProcessor SHARED
    DupliPrintProcessor.cpp
    DupliPrintProcessor.h
    DupliPrintProcessor.def
)

# Link Windows libraries
target_link_libraries(DupliPrintProcessor
    winspool
    gdi32
    user32
)

# Set DLL properties
set_target_properties(DupliPrintProcessor PROPERTIES
    OUTPUT_NAME "DupliPrintProcessor"
    PREFIX ""
)

# Install target
install(TARGETS DupliPrintProcessor
    RUNTIME DESTINATION bin
    LIBRARY DESTINATION lib
)
