#!/usr/bin/python2
import markovify
import sys

with open(sys.argv[1]) as f:
    text = f.read()

text_model = markovify.Text(text)

for i in range(200):
	print(text_model.make_short_sentence(240))
