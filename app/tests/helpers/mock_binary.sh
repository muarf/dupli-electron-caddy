#!/bin/bash
# Mock binary for Ghostscript/ImageMagick
# It detects the output file from the arguments and touches it.

echo "Mock binary called with args: $@"

# Try to find output file in arguments
# For Ghostscript: -sOutputFile="path/to/output.png" or -sOutputFile="path/to/page_%d.png"
# For ImageMagick: usually the last argument or after some flags

for arg in "$@"; do
    if [[ $arg == -sOutputFile=* ]]; then
        OUT=$(echo $arg | cut -d'=' -f2 | sed 's/"//g')
        # If it's a pattern, just touch page_1.png (or page_0.png)
        if [[ $OUT == *%d* ]]; then
            # PCL/XPS renumeration starts at 1 in GS, then PHP renunmbers to 0
            # So let's create page_1.png
            # We also need to handle the directory check in PHP
            REAL_OUT=$(echo $OUT | sed 's/%d/1/')
            touch "$REAL_OUT"
            echo "Mock: Touched $REAL_OUT"
        else
            touch "$OUT"
            echo "Mock: Touched $OUT"
        fi
    fi
done

# Special case for ImageMagick which doesn't use -sOutputFile
if [[ "$*" == *"density"* ]]; then
    # It's likely the EMF conversion
    # Command was: "magick" -density 72 "temp.emf" -background white -flatten "output.png" 2>&1
    # Iterate to find the .png file among arguments
    for arg in "$@"; do
        if [[ $arg == *.png ]]; then
            touch "$arg"
            echo "Mock (IM): Touched $arg"
            break
        fi
    done
fi

exit 0
