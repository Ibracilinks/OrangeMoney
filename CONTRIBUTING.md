# Contributing

Thanks for taking the time to contribute!

## Requirements

- PHP 8.3, 8.4, or 8.5
- Composer

## Getting started

```bash
git clone git@github.com:Ibracilinks/OrangeMoney.git
cd OrangeMoney
composer install
composer test
```

The test suite boots Laravel with [Orchestra Testbench](https://packages.tools/testbench) and mocks Orange Money's HTTP responses, so no credentials or real payments are needed to run it.

## Making a change

1. Fork the repository and create a branch for your change.
2. Keep pull requests focused: one fix or one feature per PR is easier to review than several unrelated changes bundled together.
3. Add or update tests for any behavior you change. `composer test` must pass before you open the PR; CI runs it again on PHP 8.3 through 8.5, plus the lowest supported dependency versions.
4. Update the README when you change configuration, public methods, or behavior it documents.
5. Open the pull request against `master` with a clear description of the problem and the fix.

## Reporting a bug or requesting a feature

Please [open an issue](https://github.com/Ibracilinks/OrangeMoney/issues) with:

- What you expected to happen, and what happened instead.
- Steps to reproduce, including the package version and PHP/Laravel versions.
- Any relevant error message or stack trace. Redact your `OM_AUTH_HEADER` and `OM_MERCHANT_KEY` if you paste configuration.

## Other ways to help

Sample code covering real-world corner cases (error handling, retries, queued payment checks, etc.) is always welcome, either as a PR to the README or as a link shared in an issue.

For a general introduction to contributing to open source projects, see GitHub's [guide](https://docs.github.com/en/get-started/exploring-projects-on-github/contributing-to-a-project).
