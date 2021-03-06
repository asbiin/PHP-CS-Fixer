===========================
Rule set ``@Symfony:risky``
===========================

Rules that follow the official `Symfony Coding Standards <https://symfony.com/doc/current/contributing/code/standards.html>`_ This set contains rules that are risky.

Rules
-----

- `dir_constant <./../rules/language_construct/dir_constant.rst>`_
- `ereg_to_preg <./../rules/alias/ereg_to_preg.rst>`_
- `error_suppression <./../rules/language_construct/error_suppression.rst>`_
- `fopen_flag_order <./../rules/function_notation/fopen_flag_order.rst>`_
- `fopen_flags <./../rules/function_notation/fopen_flags.rst>`_
  config:
  ``['b_mode' => false]``
- `function_to_constant <./../rules/language_construct/function_to_constant.rst>`_
  config:
  ``['functions' => ['get_called_class', 'get_class', 'get_class_this', 'php_sapi_name', 'phpversion', 'pi']]``
- `implode_call <./../rules/function_notation/implode_call.rst>`_
- `is_null <./../rules/language_construct/is_null.rst>`_
- `modernize_types_casting <./../rules/cast_notation/modernize_types_casting.rst>`_
- `native_constant_invocation <./../rules/constant_notation/native_constant_invocation.rst>`_
  config:
  ``['fix_built_in' => false, 'include' => ['DIRECTORY_SEPARATOR', 'PHP_SAPI', 'PHP_VERSION_ID'], 'scope' => 'namespaced']``
- `native_function_invocation <./../rules/function_notation/native_function_invocation.rst>`_
  config:
  ``['include' => ['@compiler_optimized'], 'scope' => 'namespaced', 'strict' => true]``
- `no_alias_functions <./../rules/alias/no_alias_functions.rst>`_
- `no_homoglyph_names <./../rules/naming/no_homoglyph_names.rst>`_
- `no_php4_constructor <./../rules/class_notation/no_php4_constructor.rst>`_
- `no_unneeded_final_method <./../rules/class_notation/no_unneeded_final_method.rst>`_
- `non_printable_character <./../rules/basic/non_printable_character.rst>`_
- `php_unit_construct <./../rules/php_unit/php_unit_construct.rst>`_
- `php_unit_mock_short_will_return <./../rules/php_unit/php_unit_mock_short_will_return.rst>`_
- `psr4 <./../rules/basic/psr4.rst>`_
- `self_accessor <./../rules/class_notation/self_accessor.rst>`_
- `set_type_to_cast <./../rules/alias/set_type_to_cast.rst>`_
