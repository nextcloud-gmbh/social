all: dev-setup lint build-js-production test

# Dev env management
dev-setup: clean clean-dev npm-init

npm-init:
	npm install

npm-update:
	npm update

# Building
build-js:
	npm run dev

build-js-production:
	npm run build

watch-js:
	npm run watch

# Testing
test:
	npm run test

test-watch:
	npm run test:watch

test-coverage:
	npm run test:coverage

# Linting
lint:
	npm run lint

lint-fix:
	npm run lint:fix

# Cleaning
clean:
	rm -f js/social.js
	rm -f js/social.js.map

clean-dev:
	rm -rf node_modules
