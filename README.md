# MsgPhp

Main development repository.

[![Build Status](https://travis-ci.org/msgphp/msgphp.svg?branch=master)](https://travis-ci.org/msgphp/msgphp)

## Packages

- [`msgphp/domain`](https://github.com/msgphp/domain)
- [`msgphp/eav`](https://github.com/msgphp/eav)
- [`msgphp/user`](https://github.com/msgphp/user)

## Testing

Tests can be run via the helper script in `bin/phpunit`

```bash
bin/phpunit
```

To first install dependencies:

```bash
bin/composer update
```

Each of these scripts will iterate through the subtrees in `src/` and call the
respective binary, passing all options, e.g.

```bash
bin/phpunit --coverage-text
```
