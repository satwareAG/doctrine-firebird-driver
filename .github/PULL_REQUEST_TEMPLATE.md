# Summary

Describe the change and why it is needed.

## Change type

- [ ] Bug fix
- [ ] Feature
- [ ] Documentation
- [ ] CI / tooling / dependencies
- [ ] Refactor

## Target line

- [ ] `3.10.x` active maintenance line
- [ ] `4.4.x` future DBAL 4 line

## Validation

- [ ] `vendor/bin/phpcs`
- [ ] `vendor/bin/phpstan analyse src/ --level=8 --no-progress --memory-limit=1G`
- [ ] `vendor/bin/psalm --no-cache`
- [ ] `cd tests && ./phpunit.sh`
- [ ] `cd tests && ./phpunit.sh -v all` or equivalent targeted justification

## Documentation

- [ ] `README.md` updated if user-facing behavior changed
- [ ] `CHANGELOG.md` updated if release notes should mention this change
- [ ] Additional docs updated where needed

## Forward-port decision

- [ ] No forward-port needed
- [ ] Should later be forward-ported to `4.4.x`
- [ ] I added context or label for future forward-porting

## Constitution check

- [ ] No undocumented breaking change to the active DBAL line
- [ ] Tests were added or updated before implementation where applicable
- [ ] Firebird version differences were considered and documented where relevant