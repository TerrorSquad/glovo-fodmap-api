#!/usr/bin/env bash

# PHP wrapper script for VS Code extensions
# This ensures that all PHP calls go through DDEV with proper path conversion

set -euo pipefail

# Get the root directory of the git repository
ROOT_DIR=$(git rev-parse --show-toplevel)

# Convert absolute paths to container paths
converted_args=()
has_r_flag=false
r_code=""

# Process arguments looking for -r flag and path conversions
for arg in "$@"; do
  if [[ "$arg" == "-r" ]]; then
    has_r_flag=true
    converted_args+=("-r")
  elif [[ "$arg" == -r* ]]; then
    has_r_flag=true
    converted_args+=("-r")
    inline_code="${arg#-r}"
    r_code="${inline_code//$ROOT_DIR/\/var\/www\/html}"
  elif [[ "$has_r_flag" == true && "$r_code" == "" ]]; then
    # Check if this looks like a file path instead of inline code
    if [[ "$arg" == *.php ]]; then
      # This is actually a file path, not inline code
      # Convert the Laravel extension's incorrect usage of -r with filepath
      has_r_flag=false
      converted_args=("${converted_args[@]%-r}")  # Remove the -r flag
      if [[ "$arg" == "$ROOT_DIR"* ]]; then
        container_path="/var/www/html${arg#$ROOT_DIR}"
        converted_args+=("$container_path")
      else
        converted_arg="${arg//$ROOT_DIR/\/var\/www\/html}"
        converted_args+=("$converted_arg")
      fi
    else
      # This is actual inline code
      r_code="${arg//$ROOT_DIR/\/var\/www\/html}"
    fi
  elif [[ "$arg" == "$ROOT_DIR"* ]]; then
    container_path="/var/www/html${arg#$ROOT_DIR}"
    converted_args+=("$container_path")
  else
    if [[ "$arg" == *"$ROOT_DIR"* ]]; then
      converted_arg="${arg//$ROOT_DIR/\/var\/www\/html}"
      converted_args+=("$converted_arg")
    else
      converted_args+=("$arg")
    fi
  fi
done

# Execute in DDEV with Xdebug disabled
if [ "$has_r_flag" = true ]; then
  # Use temp file approach for -r flag to avoid shell variable expansion
  temp_file="/tmp/vscode_php_temp_$$.php"
  printf '<?php %s' "$r_code" | ddev exec env XDEBUG_MODE=off tee "$temp_file" > /dev/null
  ddev exec env XDEBUG_MODE=off php "$temp_file"
  ddev exec rm "$temp_file" 2>/dev/null || true
else
  # Only expand array if it has elements
  if [ ${#converted_args[@]} -eq 0 ]; then
    ddev exec env XDEBUG_MODE=off php
  else
    ddev exec env XDEBUG_MODE=off php "${converted_args[@]}"
  fi
fi
