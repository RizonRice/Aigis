#!/usr/bin/python2
import markovify

with open("/usr/aigis/plugins/etc/markov.txt") as f:
    text = f.read()

text_model = markovify.Text(text)

for i in range(200):
	print(text_model.make_short_sentence(140))
