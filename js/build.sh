#!/bin/bash

cat *.js > temp
cat lib/kalendae/kalendae.standalone.min.js >> temp
uglifyjs temp > archetype.min.js
rm temp
