.PHONY: clean

wordpress-traffic-monitor:
	zip -r wordpress-traffic-monitor . -x Makefile .git/ .gitignore .git/**\* *.swp README.md

clean:
	rm -rf wordpress-traffic-monitor.zip
