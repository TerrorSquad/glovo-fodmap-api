#!/usr/bin/env bash

# Laravel IDE Runner script that executes PHP commands in DDEV with Xdebug disabled
# This prevents Xdebug connection warnings that interfere with Laravel IDE discovery
# Also converts absolute host paths to relative paths for DDEV container compatibility

set -euo pipefail # Exit on error, undefined variables, and pipe failures

# Get the root directory of the git repository
ROOT_DIR=$(git rev-parse --show-toplevel)

# Check if DDEV is available by testing if .ddev directory exists
DDEV_AVAILABLE=false
if [ -d "$ROOT_DIR/.ddev" ]; then
  DDEV_AVAILABLE=true
fi

#
# Main execution function that routes commands based on environment
#
function run_command() {
  if [ "$DDEV_AVAILABLE" = false ]; then
    # DDEV not available, run command directly on host
    execute_command_directly "$@"
  else
    # DDEV available, run command in container with Xdebug disabled and path conversion
    execute_command_in_ddev "$@"
  fi
}

#
# Execute command directly on the host system
#
function execute_command_directly() {
  "$@"
}

#
# Execute command in DDEV container with Xdebug disabled and path conversion
#
function execute_command_in_ddev() {
  local project_name
  local current_hostname

  # Extract project name from DDEV configuration
  project_name=$(grep "name: " "$ROOT_DIR/.ddev/config.yaml" | head -1 | cut -f 2 -d ' ')
  current_hostname=$(hostname)

  # Convert absolute paths to container paths for arguments
  local converted_args=()
  local has_r_flag=false
  local r_code=""
  
  # Process arguments looking for -r flag and path conversions
  for arg in "$@"; do
    # Handle different argument patterns
    if [[ "$arg" == "-r" ]]; then
      # -r flag is separate, we'll handle the next argument as code
      has_r_flag=true
      converted_args+=("-r")
    elif [[ "$arg" == -r* ]]; then
      # -r with attached code
      has_r_flag=true
      converted_args+=("-r")
      inline_code="${arg#-r}"
      # Convert paths in the PHP code
      r_code="${inline_code//$ROOT_DIR/\/var\/www\/html}"
    elif [[ "$has_r_flag" == true && "$r_code" == "" ]]; then
      # This is the code argument following -r
      r_code="${arg//$ROOT_DIR/\/var\/www\/html}"
    elif [[ "$arg" == "$ROOT_DIR"* ]]; then
      # Convert absolute path to container absolute path
      container_path="/var/www/html${arg#$ROOT_DIR}"
      converted_args+=("$container_path")
    else
      # Check if argument contains our project path anywhere (for complex strings)
      if [[ "$arg" == *"$ROOT_DIR"* ]]; then
        converted_arg="${arg//$ROOT_DIR/\/var\/www\/html}"
        converted_args+=("$converted_arg")
      else
        converted_args+=("$arg")
      fi
    fi
  done

  # Check if we're already inside the DDEV web container
  if [ "$current_hostname" == "${project_name}-web" ]; then
    # Already inside container, execute directly with Xdebug disabled
    if [ "$has_r_flag" = true ]; then
      # Create a temporary file to avoid shell variable expansion
      local temp_file
      temp_file=$(mktemp)
      printf '<?php %s' "$r_code" > "$temp_file"
      XDEBUG_MODE=off php "$temp_file"
      rm "$temp_file"
    else
      XDEBUG_MODE=off php "${converted_args[@]}"
    fi
  else
    # Outside container, use ddev exec to run command inside with Xdebug disabled
    if [ "$has_r_flag" = true ]; then
      # Create a temporary file to avoid shell variable expansion
      local temp_file="/tmp/laravel_ide_temp_$$.php"
      printf '<?php %s' "$r_code" | ddev exec env XDEBUG_MODE=off tee "$temp_file" > /dev/null
      ddev exec env XDEBUG_MODE=off php "$temp_file"
      ddev exec rm "$temp_file" 2>/dev/null || true
    else
      ddev exec env XDEBUG_MODE=off php "${converted_args[@]}"
    fi
  fi
}

# Execute the main function with all command line arguments
run_command "$@"
