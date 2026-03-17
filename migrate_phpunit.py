import os
import re

def migrate_file(filepath):
    with open(filepath, 'r') as f:
        content = f.read()

    new_content = content
    
    # 1. Define PHPUnit attributes and their metadata counterparts
    attr_map = {
        'Before': r'@before',
        'After': r'@after',
        'BeforeClass': r'@beforeClass',
        'AfterClass': r'@afterClass',
        'DataProvider': r'@dataProvider\s+(\w+)',
        'Group': r'@group\s+([\w-]+)',
        'RequiresPhpExtension': r'@requires\s+extension\s+(\w+)',
        'CoversClass': r'@covers\s+([\w\\]+)',
        'Small': r'@small',
        'Medium': r'@medium',
        'Large': r'@large'
    }

    modified = False
    
    # Use re.DOTALL to match across multiple lines in docblocks
    # This pattern matches any /** ... */ block
    for match in re.finditer(r'/\*\*(.*?)\*/', new_content, re.DOTALL):
        docblock = match.group(0)
        inner_content = match.group(1)
        
        attributes_to_add = []
        new_inner = inner_content
        
        # Check for each attribute in the docblock
        for attr_name, pattern in attr_map.items():
            # Find all occurrences of the pattern in this docblock
            for attr_match in re.finditer(pattern, inner_content):
                # Construct the attribute
                if '(' in pattern: # Has arguments
                    args = attr_match.groups()
                    if len(args) == 1:
                        # Special handling for CoversClass to use ::class if possible
                        if attr_name == 'CoversClass':
                            # If we can find the class name in use statements, use ::class
                            class_name = args[0].split('\\')[-1]
                            if class_name in new_content:
                                attr_text = f"#[{attr_name}({class_name}::class)]"
                            else:
                                attr_text = f"#[{attr_name}('\\\\{args[0]}')]" # Use fully qualified string
                        else:
                            attr_text = f"#[{attr_name}('{args[0]}')]"
                    else:
                        # Should not happen with current patterns
                        attr_text = f"#[{attr_name}]"
                else:
                    attr_text = f"#[{attr_name}]"
                
                attributes_to_add.append(attr_text)
                
                # Remove the annotation from the docblock
                # Match the line containing the annotation including leading * and spaces
                line_pattern = r'\n\s*\*\s*' + re.escape(attr_match.group(0)) + r'\s*'
                new_inner = re.sub(line_pattern, '\n', new_inner)
                
                # Also handle single-line case if docblock is just /** @... */
                new_inner = re.sub(r'^\s*' + re.escape(attr_match.group(0)) + r'\s*$', '', new_inner)

        if attributes_to_add:
            # If docblock becomes nearly empty (just whitespace and *), remove it
            if not re.search(r'[a-zA-Z0-9]', new_inner):
                replacement = '\n'.join(attributes_to_add)
            else:
                # Clean up multiple newlines in docblock
                new_inner = re.sub(r'\n\s*\n', '\n', new_inner)
                replacement = f"/**{new_inner}*/\n" + '\n'.join(attributes_to_add)
            
            new_content = new_content.replace(docblock, replacement)
            modified = True

    if not modified:
        return False

    # 2. Add use statements
    needed_uses = {
        '#[Before]': 'use PHPUnit\\Framework\\Attributes\\Before;',
        '#[After]': 'use PHPUnit\\Framework\\Attributes\\After;',
        '#[BeforeClass]': 'use PHPUnit\\Framework\\Attributes\\BeforeClass;',
        '#[AfterClass]': 'use PHPUnit\\Framework\\Attributes\\AfterClass;',
        '#[DataProvider': 'use PHPUnit\\Framework\\Attributes\\DataProvider;',
        '#[Group': 'use PHPUnit\\Framework\\Attributes\\Group;',
        '#[RequiresPhpExtension': 'use PHPUnit\\Framework\\Attributes\\RequiresPhpExtension;',
        '#[CoversClass': 'use PHPUnit\\Framework\\Attributes\\CoversClass;',
        '#[Small]': 'use PHPUnit\\Framework\\Attributes\\Small;',
        '#[Medium]': 'use PHPUnit\\Framework\\Attributes\\Medium;',
        '#[Large]': 'use PHPUnit\\Framework\\Attributes\\Large;',
    }

    added_uses = []
    for attr, use_stmt in needed_uses.items():
        if attr in new_content and use_stmt not in new_content:
            added_uses.append(use_stmt)

    if added_uses:
        # Find the last use statement or namespace
        uses = re.findall(r'^use .*?;', new_content, re.MULTILINE)
        if uses:
            last_use = uses[-1]
            new_content = new_content.replace(last_use, last_use + '\n' + '\n'.join(added_uses))
        else:
            namespace = re.search(r'^namespace .*?;', new_content, re.MULTILINE)
            if namespace:
                last_ns = namespace.group(0)
                new_content = new_content.replace(last_ns, last_ns + '\n\n' + '\n'.join(added_uses))

    with open(filepath, 'w') as f:
        f.write(new_content)
    return True

test_dir = 'tests'
for root, dirs, files in os.walk(test_dir):
    for file in files:
        if file.endswith('.php'):
            filepath = os.path.join(root, file)
            if migrate_file(filepath):
                print(f"Migrated {filepath}")
