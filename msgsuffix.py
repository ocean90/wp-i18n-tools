#!/usr/bin/env python
"""
suffixpot.py -- adds a suffix to all message ids in a pot file

$Id$
"""
from __future__ import with_statement
import sys
import fileinput
import optparse

def main(*args):
	usage = "usage: msgsuffix -s SUFFIX [-o output] potfile"
	parser = optparse.OptionParser(usage)
	parser.add_option("-s", "--suffix", dest="suffix", metavar="SUFFIX",
			help="add SUFFIX after every msgid")
	parser.add_option("-o", "--output", dest="outfile", metavar="FILE",
			help="write output to FILE, will use STDOUT if omitted")
	(options, args) = parser.parse_args()
	if 0 == len(args):
		parser.error("You should specifiy a pot file to work on.")
	if 1 != len(args):
		parser.error("You should enter exactly 1 pot file.")
	if not options.suffix:
		parser.error("You should enter a suffix.")
	suffix_pot(args[0], options.suffix, options.outfile)	


def suffix_pot(potfn, suffix, outfn=None):
	in_msgid = False
	last = None

	with open(potfn) as pot:
		with open(outfn, 'w') if outfn else sys.stdout as out:
			for line in pot:
				if line.startswith('msgstr') and last != 'msgid ""':
					print >>out, last[:-1] + suffix + '"'
				elif last != None:
					print >>out, last
				last = line.rstrip()
			print >>out, last

if __name__ == "__main__":
	main()
