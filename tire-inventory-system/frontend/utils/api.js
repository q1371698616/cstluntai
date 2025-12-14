// API 配置
const API_BASE_URL = '/backend/api';

// 存储
const Storage = {
    set: (key, value) => {
        localStorage.setItem(key, JSON.stringify(value));
    },
    get: (key) => {
        const value = localStorage.getItem(key);
        return value ? JSON.parse(value) : null;
    },
    remove: (key) => {
        localStorage.removeItem(key);
    },
    clear: () => {
        localStorage.clear();
    }
};

// HTTP 请求封装
const request = async (url, options = {}) => {
    const token = Storage.get('token');

    const defaultOptions = {
        headers: {
            'Content-Type': 'application/json',
        }
    };

    if (token) {
        defaultOptions.headers['Authorization'] = `Bearer ${token}`;
    }

    const finalOptions = {
        ...defaultOptions,
        ...options,
        headers: {
            ...defaultOptions.headers,
            ...(options.headers || {})
        }
    };

    try {
        const response = await fetch(url, finalOptions);
        const data = await response.json();

        if (data.code === 401) {
            // Token 过期，清除并跳转登录
            Storage.clear();
            showLoginPage();
            showToast('登录已过期，请重新登录');
            throw new Error('Unauthorized');
        }

        return data;
    } catch (error) {
        console.error('Request error:', error);
        throw error;
    }
};

// API 方法
const API = {
    // 用户认证
    auth: {
        login: (username, password) => {
            return request(`${API_BASE_URL}/auth.php?action=login`, {
                method: 'POST',
                body: JSON.stringify({ username, password })
            });
        },
        register: (userData) => {
            return request(`${API_BASE_URL}/auth.php?action=register`, {
                method: 'POST',
                body: JSON.stringify(userData)
            });
        },
        me: () => {
            return request(`${API_BASE_URL}/auth.php?action=me`);
        },
        changePassword: (oldPassword, newPassword) => {
            return request(`${API_BASE_URL}/auth.php?action=change-password`, {
                method: 'PUT',
                body: JSON.stringify({
                    old_password: oldPassword,
                    new_password: newPassword
                })
            });
        },
        stats: () => {
            return request(`${API_BASE_URL}/auth.php?action=stats`);
        }
    },

    // 分类管理
    categories: {
        getAll: () => {
            return request(`${API_BASE_URL}/categories.php`);
        },
        getByLevel: (level, parentId = null) => {
            let url = `${API_BASE_URL}/categories.php?level=${level}`;
            if (parentId) {
                url += `&parent_id=${parentId}`;
            }
            return request(url);
        }
    },

    // 商品管理
    products: {
        list: (params = {}) => {
            const query = new URLSearchParams(params).toString();
            return request(`${API_BASE_URL}/products.php?${query}`);
        },
        detail: (id) => {
            return request(`${API_BASE_URL}/products.php?id=${id}`);
        },
        add: (productData) => {
            return request(`${API_BASE_URL}/products.php`, {
                method: 'POST',
                body: JSON.stringify(productData)
            });
        },
        update: (productData) => {
            return request(`${API_BASE_URL}/products.php`, {
                method: 'PUT',
                body: JSON.stringify(productData)
            });
        },
        delete: (id) => {
            return request(`${API_BASE_URL}/products.php?id=${id}`, {
                method: 'DELETE'
            });
        }
    },

    // 条形码管理
    barcodes: {
        list: (params = {}) => {
            const query = new URLSearchParams(params).toString();
            return request(`${API_BASE_URL}/barcodes.php?${query}`);
        },
        getByBarcode: (barcode) => {
            return request(`${API_BASE_URL}/barcodes.php?barcode=${barcode}`);
        },
        add: (barcodeData) => {
            return request(`${API_BASE_URL}/barcodes.php`, {
                method: 'POST',
                body: JSON.stringify(barcodeData)
            });
        },
        update: (barcodeData) => {
            return request(`${API_BASE_URL}/barcodes.php`, {
                method: 'PUT',
                body: JSON.stringify(barcodeData)
            });
        },
        delete: (id) => {
            return request(`${API_BASE_URL}/barcodes.php?id=${id}`, {
                method: 'DELETE'
            });
        }
    },

    // 库存操作
    inventory: {
        inbound: (barcode, quantity, remark = '') => {
            return request(`${API_BASE_URL}/inventory.php?action=inbound`, {
                method: 'POST',
                body: JSON.stringify({ barcode, quantity, remark })
            });
        },
        outbound: (barcode, quantity, licensePlate = '', licensePlateImage = '', remark = '') => {
            return request(`${API_BASE_URL}/inventory.php?action=outbound`, {
                method: 'POST',
                body: JSON.stringify({
                    barcode,
                    quantity,
                    license_plate: licensePlate,
                    license_plate_image: licensePlateImage,
                    remark
                })
            });
        },
        batchInbound: (items) => {
            return request(`${API_BASE_URL}/inventory.php?action=batch-inbound`, {
                method: 'POST',
                body: JSON.stringify({ items })
            });
        },
        batchOutbound: (items, licensePlate = '', licensePlateImage = '') => {
            return request(`${API_BASE_URL}/inventory.php?action=batch-outbound`, {
                method: 'POST',
                body: JSON.stringify({
                    items,
                    license_plate: licensePlate,
                    license_plate_image: licensePlateImage
                })
            });
        }
    },

    // 记录查询
    records: {
        inbound: (params = {}) => {
            const query = new URLSearchParams({ ...params, type: 'inbound' }).toString();
            return request(`${API_BASE_URL}/records.php?${query}`);
        },
        outbound: (params = {}) => {
            const query = new URLSearchParams({ ...params, type: 'outbound' }).toString();
            return request(`${API_BASE_URL}/records.php?${query}`);
        }
    },

    // 数据统计
    dashboard: () => {
        return request(`${API_BASE_URL}/records.php?action=dashboard`);
    },

    // 用户管理（管理员）
    users: {
        list: () => {
            return request(`${API_BASE_URL}/records.php?action=users`);
        },
        updateStatus: (userId, status) => {
            return request(`${API_BASE_URL}/records.php?action=user-status`, {
                method: 'PUT',
                body: JSON.stringify({ user_id: userId, status })
            });
        }
    },

    // 文件上传
    upload: (file, type = 'products') => {
        const formData = new FormData();
        formData.append('file', file);

        return request(`${API_BASE_URL}/upload.php?type=${type}`, {
            method: 'POST',
            headers: {}, // 清空 Content-Type，让浏览器自动设置
            body: formData
        });
    },

    uploadBase64: (base64, type = 'products') => {
        return request(`${API_BASE_URL}/upload.php?action=base64`, {
            method: 'POST',
            body: JSON.stringify({ base64, type })
        });
    }
};
