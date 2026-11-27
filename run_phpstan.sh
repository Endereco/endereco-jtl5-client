#!/bin/bash

# Define JTL shop versions to analyse against (one phpstan.jtl-shop-<version>.neon per version)
versions=("5.3.4" "5.4.1" "5.5.3" "5.6.3" "5.7.3" "5.8.2")

# Make sure the shop sources used as bootstrap files are available
bash fetch_shops.sh

output_dir=$(mktemp -d)
trap 'rm -rf "$output_dir"' EXIT

# Run all versions in parallel; each writes its output to its own file
pids=()

for version in "${versions[@]}"; do
    vendor/bin/phpstan analyse \
        -c "phpstan.jtl-shop-$version.neon" \
        --memory-limit=1G \
        --no-progress \
        > "$output_dir/$version.log" 2>&1 &
    pids+=($!)
done

# Wait for all parallel jobs and collect the failed versions
failed_versions=()

for i in "${!pids[@]}"; do
    version="${versions[$i]}"
    if wait "${pids[$i]}"; then
        echo "JTL shop $version: OK"
    else
        echo "JTL shop $version: FAILED"
        failed_versions+=("$version")
    fi
done

if [ ${#failed_versions[@]} -gt 0 ]; then
    for version in "${failed_versions[@]}"; do
        echo ""
        echo "PHPStan errors with JTL shop $version:"
        cat "$output_dir/$version.log"
    done
    exit 1
else
    echo "No PHPStan errors found."
fi
