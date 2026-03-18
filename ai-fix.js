/**
 * AI Auto-Fix Script for SonarCloud Issues
 * Uses z-ai-web-dev-sdk to automatically fix security issues
 *
 * @description This script fetches SonarCloud issues and uses AI to generate fixes
 */

const fs = require('fs');
const path = require('path');

// Check for required environment variable
if (!process.env.ZAI_API_KEY) {
    console.log('⚠️ ZAI_API_KEY not set. Skipping AI fix.');
    process.exit(0);
}

// Import z-ai-web-dev-sdk
let ZAI;
try {
    ZAI = require('z-ai-web-dev-sdk');
} catch (e) {
    console.log('⚠️ z-ai-web-dev-sdk not installed. Skipping AI fix.');
    process.exit(0);
}

const SEVERITY_ORDER = { 'BLOCKER': 1, 'CRITICAL': 2, 'MAJOR': 3, 'MINOR': 4 };

/**
 * Load SonarCloud issues from JSON file
 */
function loadSonarIssues() {
    const issuesFile = path.join(process.cwd(), 'sonar-issues.json');

    if (!fs.existsSync(issuesFile)) {
        console.log('⚠️ sonar-issues.json not found. Running in local mode.');
        return [];
    }

    try {
        const data = JSON.parse(fs.readFileSync(issuesFile, 'utf8'));
        return data.issues || [];
    } catch (e) {
        console.error('❌ Failed to parse sonar-issues.json:', e.message);
        return [];
    }
}

/**
 * Get file content safely
 */
function getFileContent(filePath) {
    const fullPath = path.join(process.cwd(), filePath);

    if (!fs.existsSync(fullPath)) {
        return null;
    }

    return fs.readFileSync(fullPath, 'utf8');
}

/**
 * Write file content safely
 */
function writeFileContent(filePath, content) {
    const fullPath = path.join(process.cwd(), filePath);
    const dir = path.dirname(fullPath);

    if (!fs.existsSync(dir)) {
        fs.mkdirSync(dir, { recursive: true });
    }

    fs.writeFileSync(fullPath, content, 'utf8');
    console.log(`✅ Fixed: ${filePath}`);
}

/**
 * Group issues by file
 */
function groupIssuesByFile(issues) {
    const grouped = {};

    for (const issue of issues) {
        // Extract file path from component
        const component = issue.component || '';
        const filePath = component.replace('rcstrue_php_payroll:', '');

        if (!grouped[filePath]) {
            grouped[filePath] = [];
        }
        grouped[filePath].push(issue);
    }

    return grouped;
}

/**
 * Generate fix prompt for AI
 */
function generateFixPrompt(filePath, issues, fileContent) {
    const issueList = issues.map(issue => {
        return `
- Line ${issue.line || 'unknown'}: ${issue.message}
  Type: ${issue.type}
  Severity: ${issue.severity}
  Rule: ${issue.rule}
`;
    }).join('\n');

    return `You are a PHP security expert. Fix the following SonarCloud security issues in this PHP file.

FILE: ${filePath}

ISSUES TO FIX:
${issueList}

CURRENT CODE:
\`\`\`php
${fileContent}
\`\`\`

FIX RULES:
1. Fix XSS vulnerabilities by using htmlspecialchars($var, ENT_QUOTES, 'UTF-8')
2. Add proper input sanitization
3. Escape all user-controlled output
4. Fix SQL injection by using prepared statements (if applicable)
5. Fix CSRF vulnerabilities by adding tokens
6. Do NOT change any business logic
7. Keep all existing functionality intact
8. Use PSR-2 coding style

OUTPUT:
Return ONLY the corrected PHP code without any explanation or markdown formatting.
The code should be complete and ready to use.`;
}

/**
 * Apply automatic fixes without AI (for common patterns)
 */
function applyAutoFixes(filePath, issues, fileContent) {
    let content = fileContent;
    let fixedCount = 0;

    // Common XSS patterns to fix automatically
    const xssPatterns = [
        // Pattern: echo $var; -> echo htmlspecialchars($var, ENT_QUOTES, 'UTF-8');
        {
            pattern: /echo\s+\$([a-zA-Z_][a-zA-Z0-9_]*)\s*;/g,
            replacement: (match, varName) => {
                // Skip if already sanitized
                if (match.includes('htmlspecialchars') || match.includes('sanitize')) {
                    return match;
                }
                fixedCount++;
                return `echo htmlspecialchars($${varName}, ENT_QUOTES, 'UTF-8');`;
            }
        },
        // Pattern: <?= $var ?> -> <?= htmlspecialchars($var, ENT_QUOTES, 'UTF-8') ?>
        {
            pattern: /<\?=\s*\$([a-zA-Z_][a-zA-Z0-9_]*)\s*\?>/g,
            replacement: (match, varName) => {
                if (match.includes('htmlspecialchars') || match.includes('sanitize')) {
                    return match;
                }
                fixedCount++;
                return `<?= htmlspecialchars($${varName}, ENT_QUOTES, 'UTF-8') ?>`;
            }
        }
    ];

    // Apply each pattern
    for (const { pattern, replacement } of xssPatterns) {
        content = content.replace(pattern, replacement);
    }

    return { content, fixedCount };
}

/**
 * Generate fix using AI
 */
async function generateAIFix(zai, filePath, issues, fileContent) {
    const prompt = generateFixPrompt(filePath, issues, fileContent);

    try {
        const completion = await zai.chat.completions.create({
            messages: [
                {
                    role: 'system',
                    content: 'You are a PHP security expert. Return only the corrected PHP code without explanations or markdown.'
                },
                {
                    role: 'user',
                    content: prompt
                }
            ],
            temperature: 0.1,
            max_tokens: 16000
        });

        let fixedCode = completion.choices[0]?.message?.content || '';

        // Remove markdown code blocks if present
        fixedCode = fixedCode.replace(/^```php\n?/i, '').replace(/\n?```$/i, '');
        fixedCode = fixedCode.replace(/^```\n?/i, '').replace(/\n?```$/i, '');

        return fixedCode.trim();
    } catch (e) {
        console.error(`❌ AI fix failed for ${filePath}:`, e.message);
        return null;
    }
}

/**
 * Main execution
 */
async function main() {
    console.log('🔧 AI Auto-Fix for SonarCloud Issues');
    console.log('=====================================\n');

    // Load issues
    const issues = loadSonarIssues();

    if (issues.length === 0) {
        console.log('✅ No issues to fix.');
        return;
    }

    console.log(`📋 Found ${issues.length} issues to process.\n`);

    // Filter to only security-related issues
    const securityIssues = issues.filter(issue =>
        issue.type === 'VULNERABILITY' ||
        issue.severity === 'BLOCKER' ||
        issue.severity === 'CRITICAL' ||
        (issue.message && (
            issue.message.toLowerCase().includes('xss') ||
            issue.message.toLowerCase().includes('injection') ||
            issue.message.toLowerCase().includes('sanitize') ||
            issue.message.toLowerCase().includes('escape') ||
            issue.message.toLowerCase().includes('security')
        ))
    );

    console.log(`🔒 ${securityIssues.length} security-related issues found.\n`);

    if (securityIssues.length === 0) {
        console.log('✅ No security issues to fix.');
        return;
    }

    // Group issues by file
    const groupedIssues = groupIssuesByFile(securityIssues);

    // Initialize AI
    let zai = null;
    try {
        zai = await ZAI.create();
        console.log('🤖 AI SDK initialized.\n');
    } catch (e) {
        console.log('⚠️ AI SDK initialization failed. Using auto-fix patterns only.\n');
    }

    let totalFixed = 0;

    // Process each file
    for (const [filePath, fileIssues] of Object.entries(groupedIssues)) {
        console.log(`📄 Processing: ${filePath} (${fileIssues.length} issues)`);

        // Skip non-PHP files
        if (!filePath.endsWith('.php')) {
            console.log('   ⏭️  Skipped (not a PHP file)\n');
            continue;
        }

        // Get current file content
        const fileContent = getFileContent(filePath);

        if (!fileContent) {
            console.log(`   ⚠️  File not found: ${filePath}\n`);
            continue;
        }

        // Try automatic fixes first
        const { content: autoFixedContent, fixedCount: autoFixedCount } = applyAutoFixes(filePath, fileIssues, fileContent);

        if (autoFixedCount > 0) {
            writeFileContent(filePath, autoFixedContent);
            totalFixed += autoFixedCount;
            console.log(`   ✅ Auto-fixed ${autoFixedCount} issues\n`);
            continue;
        }

        // If no auto-fixes and AI is available, use AI
        if (zai && fileIssues.some(i => i.severity === 'BLOCKER' || i.severity === 'CRITICAL')) {
            console.log('   🤖 Using AI to generate fix...');

            const fixedCode = await generateAIFix(zai, filePath, fileIssues, fileContent);

            if (fixedCode && fixedCode !== fileContent) {
                writeFileContent(filePath, fixedCode);
                totalFixed += fileIssues.length;
                console.log(`   ✅ AI-fixed ${fileIssues.length} issues\n`);
            } else {
                console.log('   ⏭️  No changes needed\n');
            }
        } else {
            console.log('   ⏭️  Skipped (requires manual review)\n');
        }
    }

    console.log(`\n🎉 Total issues fixed: ${totalFixed}`);

    // Generate summary report
    const report = {
        timestamp: new Date().toISOString(),
        totalIssues: issues.length,
        securityIssues: securityIssues.length,
        fixedIssues: totalFixed,
        filesProcessed: Object.keys(groupedIssues).length
    };

    fs.writeFileSync('ai-fix-report.json', JSON.stringify(report, null, 2));
    console.log('📊 Report saved to ai-fix-report.json');
}

// Run main function
main().catch(error => {
    console.error('❌ Error:', error.message);
    process.exit(1);
});
