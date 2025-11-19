# Commit Rules & Guidelines

Simple rules for making commits in this code base.

---

## 📦 Commit Grouping Rules

### Maximum Files per Commit: 5
- **Group related files** that implement a complete feature
- **Keep commits focused** on one specific change
- **Don't mix unrelated changes** in the same commit

### When to Group Files:
- ✅ Same feature implementation (Model + Resource + Controller + Request + Component)
- ✅ Related bug fixes across multiple files
- ✅ Refactoring that affects multiple related components

### When to Commit Separately:
- ✅ Isolated bug fixes
- ✅ Documentation updates
- ✅ Style changes
- ✅ Unrelated features

---

## 🏷️ Commit Message Format

### Structure:
```
type: brief description

- bullet point for each file/change
- keep it concise and clear
```

### Commit Types:
- `feat:` New features
- `fix:` Bug fixes  
- `refactor:` Code improvements
- `docs:` Documentation
- `test:` Test updates
- `style:` Code style
- `perf:` Performance
- `chore:` Maintenance

---

## 📝 Examples

### ✅ Good - Grouped Related Files:
```
feat: implement user management CRUD

- Add UserModel with relationships
- Create UserResource with ID hashing
- Add UserCreateRequest validation
- Implement UserCreateModal component
- Update PanelUsersController
```

### ✅ Good - Single File:
```
fix: resolve date parsing in booking calendar

- Fix timezone handling in DateSelectionCalendar.tsx
```

### ❌ Bad - Too Many Unrelated Files:
```
feat: various improvements

- Add user management
- Fix booking calendar
- Update documentation
- Refactor modal components
- Add new tests
- Update styling
```

### ❌ Bad - Vague Description:
```
fix: stuff
```

---

## 🔄 Workflow

### Before Committing:
1. **Check what files changed**
2. **Group related files** (max 5)
3. **Write clear commit message**
4. **Test your changes**

### Git Commands:
```bash
# See what files changed
git status

# Add related files
git add file1.php file2.php file3.php

# Commit with message
git commit -m "type: description

- change 1
- change 2
- change 3"

# Push changes
git push origin branch-name
```

---

## 🎯 Best Practices

### Do:
- ✅ Group related files (max 5)
- ✅ Write descriptive commit messages
- ✅ Test before committing
- ✅ Use English for all messages
- ✅ Keep commits focused

### Don't:
- ❌ Commit too many unrelated files
- ❌ Use vague commit messages
- ❌ Mix features and bug fixes
- ❌ Commit without testing
- ❌ Use non-English messages

---

## 📋 Quick Checklist

Before committing, ask:
- [ ] Are these files related? (max 5)
- [ ] Is the commit message clear?
- [ ] Does it follow the format?
- [ ] Have I tested the changes?
- [ ] Is the commit focused on one thing?

---

**Remember**: Keep commits small, focused, and well-described. When in doubt, split into multiple commits. 
